<?php

namespace App\Support\Reports;

use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Expense;
use App\Models\Hospitalization;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\Vaccination;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las cifras de los reportes (§13). Una sola definición para toda la aplicación:
 *
 * - Ingresos: dinero que entró por cobros, menos lo reembolsado.
 * - Ventas: lo vendido, descontando lo devuelto (cobrado o no).
 * - Costo: unidades vendidas al precio de compra actual del producto.
 * - Utilidad estimada: ingresos − costo − gastos.
 *
 * Las ventas anuladas no cuentan en nada.
 */
class ReportData
{
    /** Colores de la paleta validada (scripts/validate_palette de la guía de gráficos), en orden fijo. */
    public const SLOTS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300'];

    /** Color fijo de los dos protagonistas del dinero: nunca cambia entre gráficos. */
    public const COLOR_INCOME = '#2a78d6';

    public const COLOR_EXPENSE = '#eb6834';

    /** Importe de la línea tras devoluciones y con el descuento general de la venta repartido. */
    private const NET_ITEM = '1.0 * sale_items.total * (sale_items.quantity - sale_items.returned_quantity) / sale_items.quantity'
        .' * (case when sales.subtotal > 0 then 1.0 * sales.total / sales.subtotal else 1 end)';

    private const NET_QUANTITY = 'sale_items.quantity - sale_items.returned_quantity';

    public function __construct(private readonly ReportPeriod $period) {}

    // ---- Definiciones compartidas (también las usa el panel de inicio) ----

    /** @return array<string, float> día => dinero que entró */
    public static function incomeByDay(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): array
    {
        $payments = SalePayment::query()->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sale_payments.paid_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('date(sale_payments.paid_at) as day, sum(sale_payments.amount) as amount')
            ->groupBy('day')->pluck('amount', 'day');

        $refunds = SaleReturn::query()->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sale_returns.created_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('date(sale_returns.created_at) as day, sum(sale_returns.amount) as amount')
            ->groupBy('day')->pluck('amount', 'day');

        $income = [];
        foreach ($payments->keys()->merge($refunds->keys())->unique() as $day) {
            $income[$day] = round((float) ($payments[$day] ?? 0) - (float) ($refunds[$day] ?? 0), 2);
        }

        return $income;
    }

    public static function income(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): float
    {
        return round(array_sum(self::incomeByDay($from, $to, $branchId)), 2);
    }

    /** Costo de los productos vendidos en el período, al precio de compra actual. */
    public static function productCost(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): float
    {
        return round((float) self::saleItems($from, $to, $branchId)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('sum(('.self::NET_QUANTITY.') * products.purchase_price) as cost')
            ->value('cost'), 2);
    }

    public static function expenses(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): float
    {
        return round((float) self::expenseQuery($from, $to, $branchId)->sum('amount'), 2);
    }

    /** @return array{income: float, cost: float, expenses: float, profit: float} */
    public static function totals(CarbonInterface $from, CarbonInterface $to, ?int $branchId = null): array
    {
        $income = self::income($from, $to, $branchId);
        $cost = self::productCost($from, $to, $branchId);
        $expenses = self::expenses($from, $to, $branchId);

        return ['income' => $income, 'cost' => $cost, 'expenses' => $expenses, 'profit' => round($income - $cost - $expenses, 2)];
    }

    private static function saleItems(CarbonInterface $from, CarbonInterface $to, ?int $branchId): Builder
    {
        return SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId));
    }

    private static function expenseQuery(CarbonInterface $from, CarbonInterface $to, ?int $branchId): Builder
    {
        return Expense::query()
            ->whereDate('expenses.spent_at', '>=', $from->toDateString())
            ->whereDate('expenses.spent_at', '<=', $to->toDateString())
            ->when($branchId, fn ($q) => $q->where('expenses.branch_id', $branchId));
    }

    // ---- Secciones ----

    /** @return array<string, mixed> */
    public function finance(): array
    {
        $p = $this->period;
        $totals = self::totals($p->from, $p->to, $p->branchId);
        $previous = $p->previousFrom ? self::totals($p->previousFrom, $p->previousTo, $p->branchId) : null;

        $expensesByDay = self::expenseQuery($p->from, $p->to, $p->branchId)
            ->selectRaw('date(expenses.spent_at) as day, sum(expenses.amount) as amount')
            ->groupBy('day')->pluck('amount', 'day');

        $receivables = Sale::with('client')->receivable()->when($p->branchId, fn ($q) => $q->where('branch_id', $p->branchId))->get();
        $payables = Purchase::with('supplier')->payable()->when($p->branchId, fn ($q) => $q->where('branch_id', $p->branchId))->get();

        return [
            'totals' => $totals,
            'previous' => $previous,
            'deltas' => [
                'income' => self::delta($totals['income'], $previous['income'] ?? null),
                'cost' => self::delta($totals['cost'], $previous['cost'] ?? null, upIsGood: false),
                'expenses' => self::delta($totals['expenses'], $previous['expenses'] ?? null, upIsGood: false),
                'profit' => self::delta($totals['profit'], $previous['profit'] ?? null),
            ],
            'chart' => [
                'labels' => array_values($p->buckets()),
                'format' => 'money',
                'kind' => 'line',
                'series' => [
                    ['name' => 'Ingresos', 'color' => self::COLOR_INCOME, 'values' => $p->series(self::incomeByDay($p->from, $p->to, $p->branchId))],
                    ['name' => 'Gastos', 'color' => self::COLOR_EXPENSE, 'values' => $p->series($expensesByDay)],
                ],
            ],
            'byMethod' => $this->incomeByMethod(),
            'expensesByCategory' => $this->rank(self::expenseQuery($p->from, $p->to, $p->branchId)
                ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->selectRaw("coalesce(expense_categories.name, 'Sin categoría') as label, sum(expenses.amount) as value")
                ->groupBy('expense_categories.name')->orderByDesc('value')->get()),
            'receivable' => round((float) $receivables->sum('balance'), 2),
            'receivableOverdue' => round((float) $receivables->filter(fn (Sale $s) => $s->isOverdue())->sum('balance'), 2),
            'debtors' => $this->rank($receivables->groupBy('client_id')
                ->map(fn ($sales) => ['label' => $sales->first()->client?->full_name ?? 'Sin cliente', 'value' => (float) $sales->sum('balance')])
                ->sortByDesc('value')->values()),
            'payable' => round((float) $payables->sum('balance'), 2),
            'creditors' => $this->rank($payables->groupBy('supplier_id')
                ->map(fn ($purchases) => ['label' => $purchases->first()->supplier?->name ?? 'Sin proveedor', 'value' => (float) $purchases->sum('balance')])
                ->sortByDesc('value')->values()),
        ];
    }

    /** @return array<string, mixed> */
    public function sales(): array
    {
        $p = $this->period;
        $items = fn (?CarbonInterface $from = null, ?CarbonInterface $to = null) => self::saleItems($from ?? $p->from, $to ?? $p->to, $p->branchId);
        $net = fn ($from, $to) => round((float) $items($from, $to)->selectRaw('sum('.self::NET_ITEM.') as net')->value('net'), 2);

        $countOf = fn ($from, $to) => Sale::where('status', 'completed')->whereBetween('created_at', [$from, $to])
            ->when($p->branchId, fn ($q) => $q->where('branch_id', $p->branchId))->count();

        $total = $net($p->from, $p->to);
        $count = $countOf($p->from, $p->to);
        $previousTotal = $p->previousFrom ? $net($p->previousFrom, $p->previousTo) : null;
        $previousCount = $p->previousFrom ? $countOf($p->previousFrom, $p->previousTo) : null;
        $ticket = $count ? round($total / $count, 2) : 0.0;
        $previousTicket = $previousCount ? round($previousTotal / $previousCount, 2) : null;

        $products = $items()->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw("products.name, products.presentation, sum(".self::NET_ITEM.') as value, sum('.self::NET_QUANTITY.') as quantity')
            ->groupBy('products.id', 'products.name', 'products.presentation')->orderByDesc('value')->get()
            ->filter(fn ($row) => (float) $row->quantity > 0)
            ->map(fn ($row) => [
                'label' => $row->presentation ? "{$row->name} — {$row->presentation}" : $row->name,
                'value' => (float) $row->value,
                'quantity' => (float) $row->quantity,
            ])->values();

        return [
            'total' => $total,
            'count' => $count,
            'ticket' => $ticket,
            'deltas' => [
                'total' => self::delta($total, $previousTotal),
                'count' => self::delta($count, $previousCount),
                'ticket' => self::delta($ticket, $previousTicket),
            ],
            'chart' => [
                'labels' => array_values($p->buckets()),
                'format' => 'money',
                'kind' => 'column',
                'series' => [['name' => 'Ventas', 'color' => self::COLOR_INCOME, 'values' => $p->series(
                    $items()->selectRaw('date(sales.created_at) as day, sum('.self::NET_ITEM.') as amount')->groupBy('day')->pluck('amount', 'day')
                )]],
            ],
            'byType' => $this->salesByType($items()),
            'products' => $this->rank($products),
            'services' => $this->rank($items()->join('services', 'services.id', '=', 'sale_items.service_id')
                ->selectRaw('services.name as label, sum('.self::NET_ITEM.') as value, sum('.self::NET_QUANTITY.') as quantity')
                ->groupBy('services.id', 'services.name')->orderByDesc('value')->get()),
            'categories' => $this->rank($items()->join('products', 'products.id', '=', 'sale_items.product_id')
                ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->selectRaw("coalesce(product_categories.name, 'Sin categoría') as label, sum(".self::NET_ITEM.') as value')
                ->groupBy('product_categories.name')->orderByDesc('value')->get()),
            'users' => $this->rank($items()->leftJoin('users', 'users.id', '=', 'sales.user_id')
                ->selectRaw("coalesce(users.name, 'Sin usuario') as label, sum(".self::NET_ITEM.') as value')
                ->groupBy('users.name')->orderByDesc('value')->get()),
            'branches' => $this->rank($items()->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
                ->selectRaw("coalesce(branches.name, 'Sin sucursal') as label, sum(".self::NET_ITEM.') as value')
                ->groupBy('branches.name')->orderByDesc('value')->get()),
        ];
    }

    /** @return array<string, mixed> */
    public function clinic(): array
    {
        $p = $this->period;
        $consultations = fn ($from = null, $to = null) => Consultation::query()->where('consultations.status', 'finished')
            ->whereBetween('consultations.started_at', [$from ?? $p->from, $to ?? $p->to])
            ->when($p->branchId, fn ($q) => $q->where('consultations.branch_id', $p->branchId));

        $vaccinations = fn ($from = null, $to = null) => Vaccination::query()
            ->whereDate('vaccinations.applied_at', '>=', ($from ?? $p->from)->toDateString())
            ->whereDate('vaccinations.applied_at', '<=', ($to ?? $p->to)->toDateString());

        $count = $consultations()->count();
        $patients = (int) $consultations()->distinct('consultations.pet_id')->count('consultations.pet_id');
        $vaccines = $vaccinations()->count();
        $hasPrevious = (bool) $p->previousFrom;

        $stays = Hospitalization::with('pet')->where('status', '!=', 'cancelled')->whereBetween('admitted_at', [$p->from, $p->to])->get();
        $closed = $stays->whereIn('status', ['discharged', 'deceased']);

        return [
            'count' => $count,
            'patients' => $patients,
            'vaccines' => $vaccines,
            'deltas' => [
                'count' => $hasPrevious ? self::delta($count, $consultations($p->previousFrom, $p->previousTo)->count()) : null,
                'patients' => $hasPrevious ? self::delta($patients, (int) $consultations($p->previousFrom, $p->previousTo)->distinct('consultations.pet_id')->count('consultations.pet_id')) : null,
                'vaccines' => $hasPrevious ? self::delta($vaccines, $vaccinations($p->previousFrom, $p->previousTo)->count()) : null,
            ],
            'chart' => [
                'labels' => array_values($p->buckets()),
                'format' => 'count',
                'kind' => 'column',
                'series' => [['name' => 'Consultas', 'color' => self::COLOR_INCOME, 'values' => $p->series(
                    $consultations()->selectRaw('date(consultations.started_at) as day, count(*) as amount')->groupBy('day')->pluck('amount', 'day')
                )]],
            ],
            'veterinarians' => $this->rank($consultations()->leftJoin('users', 'users.id', '=', 'consultations.veterinarian_id')
                ->selectRaw("coalesce(users.name, 'Sin asignar') as label, count(*) as value")
                ->groupBy('users.name')->orderByDesc('value')->get()),
            'diagnoses' => $this->rank(ConsultationDiagnosis::query()
                ->join('consultations', 'consultations.id', '=', 'consultation_diagnoses.consultation_id')
                ->where('consultations.status', 'finished')->where('consultation_diagnoses.type', 'primary')
                ->whereBetween('consultations.started_at', [$p->from, $p->to])
                ->when($p->branchId, fn ($q) => $q->where('consultations.branch_id', $p->branchId))
                ->selectRaw('consultation_diagnoses.description as label, count(*) as value')
                ->groupBy('consultation_diagnoses.description')->orderByDesc('value')->get()),
            'species' => $this->rank($consultations()->join('pets', 'pets.id', '=', 'consultations.pet_id')
                ->leftJoin('species', 'species.id', '=', 'pets.species_id')
                ->selectRaw("coalesce(species.name, 'Sin especie') as label, count(distinct consultations.pet_id) as value")
                ->groupBy('species.name')->orderByDesc('value')->get()),
            'vaccinesByType' => $this->rank($vaccinations()->join('vaccines', 'vaccines.id', '=', 'vaccinations.vaccine_id')
                ->selectRaw('vaccines.name as label, count(*) as value')
                ->groupBy('vaccines.name')->orderByDesc('value')->get()),
            'hospital' => [
                'admitted' => $stays->count(),
                'active' => Hospitalization::where('status', 'active')->count(),
                'discharged' => $closed->where('status', 'discharged')->count(),
                'deceased' => $closed->where('status', 'deceased')->count(),
                'averageDays' => $closed->count() ? round($closed->avg(fn (Hospitalization $h) => $h->stayDays()), 1) : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        $p = $this->period;
        $stock = fn () => ProductStock::query()->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->whereNull('products.deleted_at')
            ->when($p->branchId, fn ($q) => $q->where('product_stocks.branch_id', $p->branchId));

        // Una existencia negativa es un faltante por regularizar, no valor en estantería.
        $atCost = 'sum(case when product_stocks.quantity > 0 then product_stocks.quantity * products.purchase_price else 0 end)';
        $atSale = 'sum(case when product_stocks.quantity > 0 then product_stocks.quantity * products.sale_price else 0 end)';

        $totals = $stock()->selectRaw("{$atCost} as cost, {$atSale} as sale")->first();
        $batches = fn () => ProductBatch::with(['product', 'branch'])->available()->whereNotNull('expires_at')
            ->when($p->branchId, fn ($q) => $q->where('branch_id', $p->branchId));
        $today = now()->startOfDay();
        $until = now()->addDays(config('vexi.expiry_alert_days'))->endOfDay();

        $movements = StockMovement::query()->whereBetween('created_at', [$p->from, $p->to])->where('reason', '!=', 'transfer')
            ->when($p->branchId, fn ($q) => $q->where('branch_id', $p->branchId))
            ->selectRaw('reason, sum(case when quantity > 0 then quantity else 0 end) as entered, sum(case when quantity < 0 then -quantity else 0 end) as left_stock')
            ->groupBy('reason')->get()
            ->map(fn ($row) => [
                'label' => StockMovement::REASONS[$row->reason] ?? $row->reason,
                'in' => (float) $row->entered,
                'out' => (float) $row->left_stock,
            ])->sortByDesc(fn ($row) => $row['in'] + $row['out'])->values();

        return [
            'cost' => round((float) $totals?->cost, 2),
            'sale' => round((float) $totals?->sale, 2),
            'negative' => $stock()->where('product_stocks.quantity', '<', 0)->count(),
            'lowCount' => Product::lowStock()->count(),
            'low' => Product::lowStock()->orderBy('name')->get()
                ->map(fn (Product $product) => ['label' => $product->label, 'stock' => $product->stockIn(), 'min' => (float) $product->min_stock]),
            'expiredCount' => $batches()->where('expires_at', '<', $today)->count(),
            'expiringCount' => $batches()->whereBetween('expires_at', [$today, $until])->count(),
            'expiring' => $batches()->where('expires_at', '<=', $until)->orderBy('expires_at')->get(),
            'byCategory' => $this->rank($stock()->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->selectRaw("coalesce(product_categories.name, 'Sin categoría') as label, {$atCost} as value")
                ->groupBy('product_categories.name')->orderByDesc('value')->get()),
            'byProduct' => $this->rank($stock()
                ->selectRaw("products.name, products.presentation, {$atCost} as value, sum(product_stocks.quantity) as quantity")
                ->groupBy('products.id', 'products.name', 'products.presentation')->orderByDesc('value')->get()
                ->map(fn ($row) => [
                    'label' => $row->presentation ? "{$row->name} — {$row->presentation}" : $row->name,
                    'value' => (float) $row->value,
                    'quantity' => (float) $row->quantity,
                ])),
            'movements' => $movements,
            'expiryDays' => (int) config('vexi.expiry_alert_days'),
        ];
    }

    /**
     * Lo que merece una mirada hoy: no depende del período elegido.
     *
     * @return array{overdueSales: int, lowStock: int, expired: int, expiring: int}
     */
    public function pending(): array
    {
        return [
            'overdueSales' => Sale::receivable()->whereNotNull('due_date')->where('due_date', '<', now()->startOfDay())->count(),
            'lowStock' => Product::lowStock()->count(),
            'expired' => ProductBatch::available()->whereNotNull('expires_at')->where('expires_at', '<', now()->startOfDay())->count(),
            'expiring' => ProductBatch::available()->whereBetween('expires_at', [now()->startOfDay(), now()->addDays(config('vexi.expiry_alert_days'))->endOfDay()])->count(),
        ];
    }

    // ---- Apoyo ----

    private function incomeByMethod(): Collection
    {
        $p = $this->period;
        $paid = SalePayment::query()->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', 'completed')->whereBetween('sale_payments.paid_at', [$p->from, $p->to])
            ->when($p->branchId, fn ($q) => $q->where('sales.branch_id', $p->branchId))
            ->selectRaw('sale_payments.payment_method_id as method, sum(sale_payments.amount) as amount')
            ->groupBy('method')->pluck('amount', 'method');

        $refunded = SaleReturn::query()->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.status', 'completed')->whereNotNull('sale_returns.payment_method_id')
            ->whereBetween('sale_returns.created_at', [$p->from, $p->to])
            ->when($p->branchId, fn ($q) => $q->where('sales.branch_id', $p->branchId))
            ->selectRaw('sale_returns.payment_method_id as method, sum(sale_returns.amount) as amount')
            ->groupBy('method')->pluck('amount', 'method');

        // El orden y el color salen del catálogo de métodos, no del monto: cada
        // método conserva su color aunque cambie el período.
        return $this->parts(PaymentMethod::orderBy('sort')->orderBy('id')->get()
            ->map(fn (PaymentMethod $method) => [
                'label' => $method->name,
                'value' => round((float) ($paid[$method->id] ?? 0) - (float) ($refunded[$method->id] ?? 0), 2),
            ]));
    }

    private function salesByType(Builder $items): Collection
    {
        $amounts = $items->selectRaw('sale_items.type as type, sum('.self::NET_ITEM.') as value')->groupBy('type')->pluck('value', 'type');

        return $this->parts(collect(SaleItem::TYPES)
            ->map(fn (string $label, string $type) => ['label' => $label, 'value' => round((float) ($amounts[$type] ?? 0), 2)])
            ->values());
    }

    /**
     * Partes de un todo con su color fijo. Más de seis se agrupan en "Otros".
     *
     * @param  Collection<int, array{label: string, value: float}>  $rows
     */
    private function parts(Collection $rows): Collection
    {
        if ($rows->count() > count(self::SLOTS)) {
            $keep = count(self::SLOTS) - 1;
            $rows = $rows->take($keep)->push(['label' => 'Otros', 'value' => round($rows->slice($keep)->sum('value'), 2)]);
        }

        $total = $rows->sum(fn ($row) => max($row['value'], 0));

        return $rows->values()
            ->map(fn ($row, $index) => $row + ['color' => self::SLOTS[$index], 'share' => $total > 0 ? max($row['value'], 0) / $total * 100 : 0])
            ->filter(fn ($row) => $row['value'] > 0)->values();
    }

    /** Filas ordenadas de una lista, cada una con su proporción frente a la mayor y al total. */
    private function rank(iterable $rows): Collection
    {
        $rows = collect($rows)->map(fn ($row) => is_array($row) ? $row : $row->only(['label', 'value', 'quantity']))
            ->map(fn ($row) => ['label' => (string) $row['label'], 'value' => round((float) $row['value'], 2), 'quantity' => isset($row['quantity']) ? (float) $row['quantity'] : null])
            ->filter(fn ($row) => $row['value'] > 0)->values();

        $max = (float) $rows->max('value');
        $total = (float) $rows->sum('value');

        return $rows->map(fn ($row) => $row + [
            'width' => $max > 0 ? $row['value'] / $max * 100 : 0,
            'share' => $total > 0 ? $row['value'] / $total * 100 : 0,
        ]);
    }

    /**
     * Comparación con el período anterior. Sin base contra la cual comparar no se
     * inventa un porcentaje.
     *
     * @return array{pct: ?float, direction: string, tone: string}|null
     */
    public static function delta(float|int $current, float|int|null $previous, bool $upIsGood = true): ?array
    {
        if ($previous === null) {
            return null;
        }

        if ((float) $previous === 0.0) {
            return ['pct' => null, 'direction' => (float) $current === 0.0 ? 'flat' : 'up', 'tone' => 'neutral'];
        }

        $pct = round(($current - $previous) / abs($previous) * 100);
        $direction = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat');
        $good = $upIsGood ? $direction === 'up' : $direction === 'down';

        return ['pct' => (float) $pct, 'direction' => $direction, 'tone' => $direction === 'flat' ? 'neutral' : ($good ? 'good' : 'bad')];
    }
}
