<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Money;
use App\Support\Reports\ReportData;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\XlsxWriter;
use App\Support\Stock;
use App\Support\Tenancy;

/**
 * Reportes y estadísticas (§13). Cada pantalla responde una pregunta del negocio;
 * todas comparten período y sucursal. Finanzas y ventas piden `reports.finance`:
 * quien solo ve lo clínico y el stock no ve dinero.
 */
class ReportController extends Controller
{
    public function index(?string $section = null)
    {
        $sections = $this->sections();
        abort_if($sections === [], 403, 'No tienes reportes disponibles en esta veterinaria.');

        if ($section === null) {
            return redirect()->route('reports.index', ['section' => array_key_first($sections)] + request()->query());
        }

        abort_unless(isset($sections[$section]), 403, 'No tienes acceso a este reporte.');

        $period = ReportPeriod::fromRequest(request());
        $data = $this->dataFor($section, $period);
        $company = app(Tenancy::class)->company();

        return view('admin.reports.index', [
            'sections' => $sections,
            'section' => $section,
            'period' => $period,
            'data' => $data,
            'branches' => Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
            'presets' => ReportPeriod::PRESETS,
            'canExport' => $this->can('reports.export'),
            'chartMeta' => [
                'symbol' => Money::symbol($company),
                'decimals' => config('vexi.currencies.'.($company?->currency ?? config('vexi.default_currency')).'.decimals', 2),
            ],
        ]);
    }

    public function export(string $section)
    {
        abort_unless(isset($this->sections()[$section]), 403, 'No tienes acceso a este reporte.');

        $period = ReportPeriod::fromRequest(request());
        $data = $this->dataFor($section, $period);
        $writer = new XlsxWriter;

        foreach ($this->sheets($section, $data, $period) as [$title, $columns, $rows]) {
            $writer->addSheet($title, $columns, $rows);
        }

        $name = sprintf('reporte-%s-%s_%s.xlsx', $section, $period->from->toDateString(), $period->to->toDateString());

        return response()->download($writer->save(), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** @return array<string, array{label: string, icon: string, question: string}> pantallas a las que este usuario puede entrar */
    protected function sections(): array
    {
        $company = app(Tenancy::class)->company();
        $money = $this->can('reports.finance') && $company?->planAllows('pos');
        $operations = $this->can('reports.view') || $this->can('reports.finance');

        return array_filter([
            'resumen' => $money ? ['label' => 'Resumen', 'icon' => 'bi-speedometer2', 'question' => '¿Cómo va tu veterinaria?'] : null,
            'finanzas' => $money ? ['label' => 'Finanzas', 'icon' => 'bi-cash-stack', 'question' => '¿Cuánto dinero entró y cuánto salió?'] : null,
            'ventas' => $money ? ['label' => 'Ventas', 'icon' => 'bi-bag-check', 'question' => '¿Qué se vende y quién lo vende?'] : null,
            'clinica' => $operations && $company?->planAllows('consultas') ? ['label' => 'Clínica', 'icon' => 'bi-clipboard2-pulse', 'question' => '¿A cuántos pacientes atendimos y por qué?'] : null,
            'inventario' => $operations && $company?->planAllows('inventario') ? ['label' => 'Inventario', 'icon' => 'bi-box-seam', 'question' => '¿Qué hay en estantería y qué necesita atención?'] : null,
        ]);
    }

    /** @return array<string, mixed> */
    protected function dataFor(string $section, ReportPeriod $period): array
    {
        $reports = new ReportData($period);
        $company = app(Tenancy::class)->company();

        $finance = function () use ($reports, $company) {
            $finance = $reports->finance();
            $finance['withExpenses'] = (bool) $company?->planAllows('caja');
            $finance['withPayables'] = (bool) $company?->planAllows('compras');

            // Sin el módulo de caja no hay gastos que mostrar.
            if (! $finance['withExpenses']) {
                unset($finance['chart']['series'][1]);
                $finance['chart']['series'] = array_values($finance['chart']['series']);
            }

            return $finance;
        };

        return match ($section) {
            'finanzas' => $finance(),
            'ventas' => $reports->sales(),
            'clinica' => $reports->clinic() + [
                'withVaccines' => (bool) $company?->planAllows('vacunas'),
                'withHospital' => (bool) $company?->planAllows('hospitalizacion'),
            ],
            'inventario' => $this->inventory($reports),
            default => $this->summary($reports, $period, $finance()),
        };
    }

    /** Quien no ve dinero tampoco ve cuánto vale el inventario. */
    protected function inventory(ReportData $reports): array
    {
        $data = $reports->inventory();
        $data['withValue'] = $this->can('reports.finance');

        if (! $data['withValue']) {
            unset($data['cost'], $data['sale'], $data['byCategory'], $data['byProduct']);
        }

        return $data;
    }

    protected function summary(ReportData $reports, ReportPeriod $period, array $finance): array
    {
        $company = app(Tenancy::class)->company();
        $sales = $reports->sales();
        $clinic = $company?->planAllows('consultas') ? $reports->clinic() : null;

        return [
            'finance' => $finance,
            'sales' => $sales,
            'clinic' => $clinic,
            'insights' => $this->insights($period, $finance, $sales, $clinic),
            'attention' => $this->attention($reports->pending(), $finance),
        ];
    }

    /**
     * La lectura del período en lenguaje claro: lo primero que se lee.
     *
     * @return array<int, array{icon: string, html: string}>
     */
    protected function insights(ReportPeriod $period, array $finance, array $sales, ?array $clinic): array
    {
        $company = app(Tenancy::class)->company();
        $money = fn ($value) => '<strong>'.e(Money::format($value, $company)).'</strong>';
        $strong = fn ($text) => '<strong>'.e($text).'</strong>';
        $totals = $finance['totals'];
        $insights = [];

        $delta = $finance['deltas']['income'];
        $versus = e((string) $period->previousLabel);
        if ($totals['income'] <= 0 && $sales['count'] === 0) {
            return [['icon' => 'bi-hourglass', 'html' => 'Todavía no hay ventas ni cobros en este período. Prueba con otro rango de fechas.']];
        }

        $insights[] = match (true) {
            $delta !== null && $delta['pct'] !== null && $delta['pct'] > 0 => ['icon' => 'bi-graph-up-arrow', 'html' => "Ingresaste {$money($totals['income'])}, <strong>{$delta['pct']} % más</strong> que {$versus}."],
            $delta !== null && $delta['pct'] !== null && $delta['pct'] < 0 => ['icon' => 'bi-graph-down-arrow', 'html' => 'Ingresaste '.$money($totals['income']).', <strong>'.abs($delta['pct'])." % menos</strong> que {$versus}."],
            $delta !== null && $delta['pct'] !== null => ['icon' => 'bi-dash-lg', 'html' => "Ingresaste {$money($totals['income'])}, igual que {$versus}."],
            default => ['icon' => 'bi-cash-coin', 'html' => "Ingresaste {$money($totals['income'])} en este período."],
        };

        if ($totals['profit'] < 0) {
            $insights[] = ['icon' => 'bi-exclamation-triangle', 'html' => 'Los costos y gastos superaron a los ingresos por '.$money(abs($totals['profit'])).'.'];
        } elseif ($totals['income'] > 0) {
            $margin = round($totals['profit'] / $totals['income'] * 100);
            $insights[] = ['icon' => 'bi-piggy-bank', 'html' => "De cada 100 que entraron, te quedaron <strong>{$margin}</strong> después de costos y gastos."];
        }

        $top = $sales['services']->concat($sales['products'])->sortByDesc('value')->first();
        if ($top) {
            $insights[] = ['icon' => 'bi-trophy', 'html' => 'Lo que más vendiste fue '.$strong($top['label']).' ('.$money($top['value']).').'];
        }

        $expense = $finance['expensesByCategory']->first();
        if ($finance['withExpenses'] && $expense) {
            $insights[] = ['icon' => 'bi-receipt', 'html' => 'Tu mayor gasto fue '.$strong($expense['label']).': <strong>'.round($expense['share']).' %</strong> del total.'];
        } elseif ($clinic && $clinic['count'] > 0) {
            $insights[] = ['icon' => 'bi-clipboard2-pulse', 'html' => "Atendiste <strong>{$clinic['count']}</strong> consultas de <strong>{$clinic['patients']}</strong> pacientes."];
        }

        return $insights;
    }

    /** @return array<int, array{icon: string, tone: string, text: string, url: ?string}> */
    protected function attention(array $pending, array $finance): array
    {
        $company = app(Tenancy::class)->company();
        $inventory = $company?->planAllows('inventario');
        $units = fn (int $n, string $one, string $many) => $n === 1 ? $one : "{$n} {$many}";

        return array_values(array_filter([
            $pending['overdueSales'] ? ['icon' => 'bi-exclamation-octagon', 'tone' => 'critical',
                'text' => $units($pending['overdueSales'], 'Una venta a crédito vencida', 'ventas a crédito vencidas').' sin cobrar',
                'url' => $this->can('sales.view') ? route('receivables.index', ['overdue' => 1]) : null] : null,
            $inventory && $pending['expired'] ? ['icon' => 'bi-calendar-x', 'tone' => 'critical',
                'text' => $units($pending['expired'], 'Un lote vencido', 'lotes vencidos').' en estantería',
                'url' => $this->can('inventory.view') ? route('expiry.index', ['tab' => 'expired']) : null] : null,
            $inventory && $pending['lowStock'] ? ['icon' => 'bi-box-seam', 'tone' => 'warning',
                'text' => $units($pending['lowStock'], 'Un producto', 'productos').' por debajo del stock mínimo',
                'url' => $this->can('products.view') ? route('products.index', ['status' => 'low']) : null] : null,
            $inventory && $pending['expiring'] ? ['icon' => 'bi-calendar-event', 'tone' => 'warning',
                'text' => $units($pending['expiring'], 'Un lote vence', 'lotes vencen').' en los próximos '.config('vexi.expiry_alert_days').' días',
                'url' => $this->can('inventory.view') ? route('expiry.index') : null] : null,
        ]));
    }

    protected function can(string $permission): bool
    {
        return (bool) auth()->user()?->hasPermissionInCompany($permission, app(Tenancy::class)->company());
    }

    /** @return array<int, array{0: string, 1: array<int, string>, 2: array<int, array<int, mixed>>}> */
    protected function sheets(string $section, array $data, ReportPeriod $period): array
    {
        $rangeLabel = $period->label();
        $chart = function (array $chart, string $title) use ($rangeLabel) {
            $rows = [];
            foreach ($chart['labels'] as $i => $label) {
                $rows[] = array_merge([(string) $label], array_map(fn ($serie) => $chart['format'] === 'money' ? (float) $serie['values'][$i] : (int) $serie['values'][$i], $chart['series']));
            }

            return ["{$title} · {$rangeLabel}", array_merge(['Período'], array_column($chart['series'], 'name')), $rows];
        };
        $amounts = fn ($rows, string $title, string $column) => ["{$title} · {$rangeLabel}", [$column, 'Importe', '% del total'],
            collect($rows)->map(fn ($r) => [$r['label'], (float) $r['value'], round($r['share'], 1)])->all()];
        $withQuantity = fn ($rows, string $title, string $column, string $quantity = 'Unidades') => ["{$title} · {$rangeLabel}", [$column, $quantity, 'Importe'],
            collect($rows)->map(fn ($r) => [$r['label'], (float) $r['quantity'], (float) $r['value']])->all()];
        $counts = fn ($rows, string $title, string $column, string $quantity = 'Cantidad') => ["{$title} · {$rangeLabel}", [$column, $quantity],
            collect($rows)->map(fn ($r) => [$r['label'], (int) $r['value']])->all()];

        $financeSheets = function (array $d) use ($chart, $amounts, $rangeLabel) {
            $summary = [['Ingresos', $d['totals']['income']], ['Costo de productos vendidos', $d['totals']['cost']]];
            if ($d['withExpenses']) {
                $summary[] = ['Gastos', $d['totals']['expenses']];
            }
            $summary[] = ['Utilidad estimada', $d['totals']['profit']];
            $summary[] = ['Cuentas por cobrar (hoy)', $d['receivable']];
            if ($d['withPayables']) {
                $summary[] = ['Cuentas por pagar (hoy)', $d['payable']];
            }

            return array_values(array_filter([
                ["Resumen financiero · {$rangeLabel}", ['Concepto', 'Importe'], $summary],
                $chart($d['chart'], 'Ingresos y gastos'),
                $amounts($d['byMethod'], 'Ingresos por método de pago', 'Método'),
                $d['withExpenses'] ? $amounts($d['expensesByCategory'], 'Gastos por categoría', 'Categoría') : null,
                $amounts($d['debtors'], 'Cuentas por cobrar', 'Cliente'),
                $d['withPayables'] ? $amounts($d['creditors'], 'Cuentas por pagar', 'Proveedor') : null,
            ]));
        };

        $salesSheets = fn (array $d) => [
            $chart($d['chart'], 'Ventas por período'),
            $amounts($d['byType'], 'Ventas por tipo', 'Tipo'),
            $withQuantity($d['products'], 'Ventas por producto', 'Producto'),
            $withQuantity($d['services'], 'Ventas por servicio', 'Servicio', 'Veces'),
            $amounts($d['categories'], 'Ventas por categoría', 'Categoría'),
            $amounts($d['users'], 'Ventas por usuario', 'Usuario'),
            $amounts($d['branches'], 'Ventas por sucursal', 'Sucursal'),
        ];

        return match ($section) {
            'resumen' => array_merge($financeSheets($data['finance']), $salesSheets($data['sales'])),
            'finanzas' => $financeSheets($data),
            'ventas' => $salesSheets($data),
            'clinica' => array_values(array_filter([
                $chart($data['chart'], 'Consultas por período'),
                $counts($data['veterinarians'], 'Consultas por veterinario', 'Veterinario', 'Consultas'),
                $counts($data['diagnoses'], 'Diagnósticos frecuentes', 'Diagnóstico', 'Casos'),
                $counts($data['species'], 'Pacientes por especie', 'Especie', 'Mascotas'),
                $data['withVaccines'] ? $counts($data['vaccinesByType'], 'Vacunas aplicadas', 'Vacuna', 'Dosis') : null,
                $data['withHospital'] ? ["Hospitalizaciones · {$rangeLabel}", ['Indicador', 'Valor'], [
                    ['Ingresos en el período', $data['hospital']['admitted']],
                    ['Internados hoy', $data['hospital']['active']],
                    ['Altas médicas', $data['hospital']['discharged']],
                    ['Fallecidos', $data['hospital']['deceased']],
                    ['Días promedio de estadía', $data['hospital']['averageDays']],
                ]] : null,
            ])),
            default => array_values(array_filter([
                isset($data['byProduct']) ? ['Valorización de inventario (hoy)', ['Producto', 'Existencia', 'Valor al costo'],
                    collect($data['byProduct'])->map(fn ($r) => [$r['label'], (float) $r['quantity'], (float) $r['value']])->all()] : null,
                isset($data['byCategory']) ? ['Valorización por categoría (hoy)', ['Categoría', 'Valor al costo', '% del total'],
                    collect($data['byCategory'])->map(fn ($r) => [$r['label'], (float) $r['value'], round($r['share'], 1)])->all()] : null,
                ['Stock mínimo (hoy)', ['Producto', 'Existencia', 'Mínimo'], collect($data['low'])->map(fn ($r) => [$r['label'], (float) $r['stock'], (float) $r['min']])->all()],
                ['Vencimientos (hoy)', ['Producto', 'Lote', 'Vence', 'Existencia', 'Sucursal'], collect($data['expiring'])
                    ->map(fn ($b) => [$b->product?->label, $b->lot, $b->expires_at->format('d/m/Y'), (float) $b->quantity, $b->branch?->name])->all()],
                ["Movimientos de inventario · {$rangeLabel}", ['Motivo', 'Entradas', 'Salidas'], collect($data['movements'])->map(fn ($r) => [$r['label'], $r['in'], $r['out']])->all()],
            ])),
        };
    }
}
