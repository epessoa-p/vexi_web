<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Expense;
use App\Models\Hospitalization;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\ClinicalCatalog;
use App\Support\Reports\ReportData;
use App\Support\Reports\ReportPeriod;
use App\Support\Stock;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;
use ZipArchive;

/**
 * Reportes: las cifras tienen una sola definición y cuadran entre sí y con el
 * panel de inicio; cada quien ve solo lo que le corresponde.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['reports.view', 'reports.finance', 'reports.export', 'sales.view', 'sales.create', 'sales.edit', 'cash.view', 'cash.create', 'products.view', 'inventory.view'];

    private const FEATURES = ['estadisticas', 'pos', 'caja', 'inventario', 'consultas', 'vacunas', 'hospitalizacion', 'compras'];

    private Company $company;

    private User $user;

    private Branch $branch;

    private PaymentMethod $cash;

    private Pet $pet;

    private CashSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-11-20 12:00:00');
        $this->travelTo('2026-11-20 12:00:00');

        $this->company = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        $this->user = $this->userInCompany($this->company, self::ALL, 'gerencia');
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        app(Tenancy::class)->runFor($this->company->id, fn () => ClinicalCatalog::seedFor($this->company));
        $this->cash = PaymentMethod::allCompanies()->where('company_id', $this->company->id)->where('type', 'cash')->firstOrFail();
        $this->pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);

        // Con el módulo de caja, cobrar exige un turno abierto.
        $caja = Caja::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Caja 1', 'active' => true]);
        $this->as()->post(route('cash.open', $caja), ['opening_amount' => 0])->assertSessionHasNoErrors();
        $this->session = CashSession::allCompanies()->where('status', 'open')->firstOrFail();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function as()
    {
        return $this->actingInCompany($this->user, $this->company);
    }

    private function inTenant(callable $callback): mixed
    {
        return app(Tenancy::class)->runFor($this->company->id, $callback);
    }

    /** Vende por el POS real y deja la venta con la fecha pedida. */
    private function sale(array $items, ?float $paid = null, ?string $at = null, array $extra = []): Sale
    {
        $total = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']) - ($extra['discount'] ?? 0);

        $this->as()->post(route('pos.store'), $extra + [
            'client_id' => $this->pet->client_id,
            'cash_session_id' => $this->session->id,
            'items' => $items,
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => $paid ?? $total]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::allCompanies()->latest('id')->firstOrFail();

        if ($at) {
            $sale->timestamps = false;
            $sale->forceFill(['created_at' => $at])->save();
            SalePayment::allCompanies()->where('sale_id', $sale->id)->update(['paid_at' => $at]);
        }

        return $sale;
    }

    private function product(float $stock = 10, array $attributes = []): Product
    {
        $product = Product::factory()->create(['company_id' => $this->company->id, 'sale_price' => 50, 'purchase_price' => 20] + $attributes);
        if ($stock > 0) {
            $this->inTenant(fn () => Stock::receive($product, $this->branch, $stock, 'initial', $product->track_batches ? ['lot' => 'L1', 'expires_at' => now()->addYear()] : []));
        }

        return $product;
    }

    private function line(float $price = 100, array $extra = []): array
    {
        return ['type' => 'other', 'description' => 'Servicio', 'quantity' => 1, 'unit_price' => $price] + $extra;
    }

    private function expense(float $amount, string $at = '2026-11-10'): Expense
    {
        return Expense::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'concept' => 'Luz', 'amount' => $amount, 'spent_at' => $at]);
    }

    private function totals(string $from = '2026-11-01', string $to = '2026-11-30', ?int $branch = null): array
    {
        return $this->inTenant(fn () => ReportData::totals(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay(), $branch));
    }

    private function period(array $query): ReportPeriod
    {
        return ReportPeriod::fromRequest(Request::create('/reportes', 'GET', $query));
    }

    public function test_sin_el_modulo_no_entra(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['pos', 'consultas']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('reports.index'))->assertForbidden();
    }

    public function test_quien_no_tiene_permiso_no_entra(): void
    {
        $cajero = $this->userInCompany($this->company, ['sales.view'], 'cajero-x');

        $this->actingInCompany($cajero, $this->company)->get(route('reports.index'))->assertForbidden();
    }

    public function test_el_veterinario_ve_clinica_y_stock_pero_no_dinero(): void
    {
        $vet = $this->userInCompany($this->company, ['reports.view'], 'vet-x');
        $as = fn () => $this->actingInCompany($vet, $this->company);

        // Entra directo a lo primero que puede ver.
        $as()->get(route('reports.index'))->assertRedirect(route('reports.index', ['section' => 'clinica']));

        foreach (['resumen', 'finanzas', 'ventas'] as $section) {
            $as()->get(route('reports.index', ['section' => $section]))->assertForbidden();
        }

        $as()->get(route('reports.index', ['section' => 'clinica']))->assertOk()->assertDontSee('Excel');
        $as()->get(route('reports.index', ['section' => 'inventario']))->assertOk()
            ->assertDontSee('Valor del inventario')->assertDontSee('¿Dónde está tu dinero?')->assertSee('Por reponer');

        // Exportar pide su permiso, y un permiso de exportar no abre lo que no se puede ver.
        $as()->get(route('reports.export', ['section' => 'clinica']))->assertForbidden();
        $exporta = $this->userInCompany($this->company, ['reports.view', 'reports.export'], 'vet-y');
        $this->actingInCompany($exporta, $this->company)->get(route('reports.export', ['section' => 'finanzas']))->assertForbidden();
    }

    public function test_ingresos_cuentan_lo_cobrado_y_no_lo_fiado(): void
    {
        $this->sale([$this->line(100)], paid: 40, at: '2026-11-10 10:00:00');

        $this->assertSame(40.0, $this->totals()['income']);

        // En ventas sí figura completa.
        $this->as()->get(route('reports.index', ['section' => 'ventas', 'preset' => 'this_month']))->assertOk()->assertSee('100.00');
    }

    public function test_la_utilidad_resta_costo_y_gastos(): void
    {
        $product = $this->product();
        $this->sale([['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 2, 'unit_price' => 50], $this->line(100)], at: '2026-11-10 10:00:00');
        $this->expense(30);

        // Ingresos 200 − costo 2×20 − gastos 30.
        $this->assertSame(['income' => 200.0, 'cost' => 40.0, 'expenses' => 30.0, 'profit' => 130.0], $this->totals());
    }

    public function test_anular_una_venta_devuelta_no_resta_dos_veces(): void
    {
        $product = $this->product();
        $sale = $this->sale([['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 2, 'unit_price' => 50], $this->line(50)]);
        $item = $sale->items()->where('type', 'product')->firstOrFail();

        // Devuelve una unidad (50 de reembolso): queda 150 de ingreso y una unidad de costo.
        $this->as()->post(route('sales.return', $sale), ['quantities' => [$item->id => 1], 'payment_method_id' => $this->cash->id, 'cash_session_id' => $this->session->id])->assertSessionHasNoErrors();
        $this->assertSame(100.0, $this->totals()['income']);
        $this->assertSame(20.0, $this->totals()['cost']);

        // Anulada no cuenta en nada: ni el cobro ni la devolución.
        $this->as()->post(route('sales.cancel', $sale), ['cancel_reason' => 'Error', 'payment_method_id' => $this->cash->id, 'cash_session_id' => $this->session->id])->assertSessionHasNoErrors();
        $this->assertSame(['income' => 0.0, 'cost' => 0.0, 'expenses' => 0.0, 'profit' => 0.0], $this->totals());
    }

    public function test_el_panel_de_inicio_da_las_mismas_cifras(): void
    {
        $product = $this->product();
        $this->sale([['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 1, 'unit_price' => 50]]);
        $this->expense(10, '2026-11-20');

        $profit = $this->totals()['profit'];
        $this->assertSame(20.0, $profit);

        $this->as()->get(route('dashboard'))->assertOk()
            ->assertViewHas('finance', fn ($finance) => (float) $finance['profit_month'] === $profit && (float) $finance['month'] === 50.0)
            ->assertSee('ingresos − costo − gastos');
    }

    public function test_ventas_reparten_descuento_general_y_descuentan_devoluciones(): void
    {
        $caro = $this->product(attributes: ['name' => 'Producto caro', 'sale_price' => 100]);
        $barato = $this->product(attributes: ['name' => 'Producto barato', 'sale_price' => 100]);

        // 300 con 30 de descuento general: cada peso vale 0,90.
        $sale = $this->sale([
            ['type' => 'product', 'product_id' => $caro->id, 'description' => 'C', 'quantity' => 2, 'unit_price' => 100],
            ['type' => 'product', 'product_id' => $barato->id, 'description' => 'B', 'quantity' => 1, 'unit_price' => 100],
        ], extra: ['discount' => 30]);

        $report = fn () => $this->inTenant(fn () => (new ReportData($this->period(['preset' => 'this_month'])))->sales());

        $this->assertSame(270.0, $report()['total']);
        $this->assertSame('Producto caro', $report()['products'][0]['label']);
        $this->assertSame(180.0, $report()['products'][0]['value']);

        $item = $sale->items()->where('product_id', $caro->id)->firstOrFail();
        $this->as()->post(route('sales.return', $sale), ['quantities' => [$item->id => 1], 'payment_method_id' => $this->cash->id, 'cash_session_id' => $this->session->id])->assertSessionHasNoErrors();

        // Las líneas y el total cuadran: lo que informa una pantalla es la suma de la otra.
        $after = $report();
        $this->assertSame(180.0, $after['total']);
        $this->assertSame(1.0, $after['products'][0]['quantity']);
        $this->assertEqualsWithDelta($after['total'], collect($after['products'])->sum('value'), 0.01);
    }

    public function test_este_mes_compara_con_los_mismos_dias_del_mes_anterior(): void
    {
        $period = $this->period([]);

        $this->assertSame('this_month', $period->preset);
        $this->assertSame('2026-11-01', $period->from->toDateString());
        $this->assertSame('2026-11-20', $period->to->toDateString());
        $this->assertSame('2026-10-01', $period->previousFrom->toDateString());
        $this->assertSame('2026-10-20', $period->previousTo->toDateString());
        $this->assertSame('day', $period->group);

        // Lo vendido después del día 20 del mes pasado no entra en la comparación.
        $this->sale([$this->line(100)], at: '2026-10-05 10:00:00');
        $this->sale([$this->line(900)], at: '2026-10-25 10:00:00');
        $this->sale([$this->line(150)], at: '2026-11-05 10:00:00');

        $finance = $this->inTenant(fn () => (new ReportData($period))->finance());
        $this->assertSame(100.0, $finance['previous']['income']);
        $this->assertSame(['pct' => 50.0, 'direction' => 'up', 'tone' => 'good'], $finance['deltas']['income']);
    }

    public function test_no_inventa_porcentajes_sin_base(): void
    {
        $this->assertSame(['pct' => null, 'direction' => 'up', 'tone' => 'neutral'], ReportData::delta(100, 0));
        $this->assertNull(ReportData::delta(100, null));
        $this->assertSame('bad', ReportData::delta(120, 100, upIsGood: false)['tone']);
        $this->assertSame('good', ReportData::delta(80, 100, upIsGood: false)['tone']);
    }

    public function test_el_periodo_tolera_entradas_malas(): void
    {
        $this->assertSame('this_month', $this->period(['preset' => 'custom', 'from' => '2026-02-31', 'to' => 'x'])->preset);
        $this->assertSame('this_month', $this->period(['preset' => 'nada'])->preset);

        $invertido = $this->period(['preset' => 'custom', 'from' => '2026-03-10', 'to' => '2026-03-01']);
        $this->assertSame('2026-03-01', $invertido->from->toDateString());
        $this->assertSame('2026-03-10', $invertido->to->toDateString());
        $this->assertSame('2026-02-19', $invertido->previousFrom->toDateString());
        $this->assertSame('2026-02-28', $invertido->previousTo->toDateString());

        // Una agrupación que no tiene sentido para el rango se ignora.
        $this->assertSame('day', $this->period(['preset' => 'this_month', 'group' => 'year'])->group);
        $this->assertSame('month', $this->period(['preset' => 'this_year', 'group' => 'day'])->group);
    }

    public function test_agrupa_con_tramos_vacios_incluidos(): void
    {
        $period = $this->period(['preset' => 'this_year']);

        $this->assertSame('month', $period->group);
        $this->assertCount(11, $period->buckets());
        $values = $period->series(['2026-01-15' => 10, '2026-01-20' => 5, '2026-11-02' => 7, '2025-12-31' => 99]);
        $this->assertSame(15.0, $values[0]);
        $this->assertSame(0.0, $values[5]);
        $this->assertSame(7.0, $values[10]);
    }

    public function test_filtra_por_sucursal(): void
    {
        $otra = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->sale([$this->line(100)]);
        $this->sale([$this->line(70)]);
        Sale::allCompanies()->latest('id')->firstOrFail()->update(['branch_id' => $otra->id]);

        $this->assertSame(170.0, $this->totals()['income']);
        $this->assertSame(70.0, $this->totals(branch: $otra->id)['income']);
    }

    public function test_clinica_cuenta_finalizadas_y_cada_mascota_una_vez(): void
    {
        $vet = $this->userInCompany($this->company, [], 'veterinarian');
        foreach ([['finished', 'Otitis'], ['finished', 'Otitis'], ['finished', 'Dermatitis'], ['in_progress', 'Sarna']] as [$status, $diagnosis]) {
            $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id, 'status' => $status, 'veterinarian_id' => $vet->id, 'started_at' => '2026-11-10 10:00:00']);
            $consultation->diagnoses()->create(['company_id' => $this->company->id, 'type' => 'primary', 'description' => $diagnosis]);
        }
        Hospitalization::factory()->create(['pet_id' => $this->pet->id, 'status' => 'discharged', 'admitted_at' => '2026-11-05 10:00:00', 'discharged_at' => '2026-11-07 10:00:00']);
        Hospitalization::factory()->create(['pet_id' => $this->pet->id, 'status' => 'cancelled', 'admitted_at' => '2026-11-05 10:00:00']);

        $clinic = $this->inTenant(fn () => (new ReportData($this->period(['preset' => 'this_month'])))->clinic());

        $this->assertSame(3, $clinic['count']);
        $this->assertSame(1, $clinic['patients']);
        $this->assertSame('Otitis', $clinic['diagnoses'][0]['label']);
        $this->assertEquals(2, $clinic['diagnoses'][0]['value']);
        $this->assertCount(2, $clinic['diagnoses']);
        $this->assertEquals(3, $clinic['veterinarians'][0]['value']);
        $this->assertSame(1, $clinic['hospital']['admitted']);
        $this->assertEquals(2.0, $clinic['hospital']['averageDays']);
    }

    public function test_inventario_valoriza_sin_contar_faltantes(): void
    {
        $this->product(10, ['name' => 'Con stock']);
        $negativo = $this->product(0, ['name' => 'Negativo']);
        ProductStock::create(['company_id' => $this->company->id, 'product_id' => $negativo->id, 'branch_id' => $this->branch->id, 'quantity' => -3]);

        $inventory = $this->inTenant(fn () => (new ReportData($this->period([])))->inventory());

        $this->assertSame(200.0, $inventory['cost']);
        $this->assertSame(500.0, $inventory['sale']);
        $this->assertSame(1, $inventory['negative']);
    }

    public function test_no_mezcla_datos_de_otra_veterinaria(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);
        $metodo = PaymentMethod::create(['company_id' => $ajena->id, 'name' => 'Efectivo', 'type' => 'cash', 'active' => true]);
        $venta = app(Tenancy::class)->runFor($ajena->id, fn () => Sale::create(['company_id' => $ajena->id, 'number' => 1, 'status' => 'completed', 'total' => 999, 'subtotal' => 999,
            'branch_id' => Branch::factory()->create(['company_id' => $ajena->id])->id]));
        SalePayment::create(['company_id' => $ajena->id, 'sale_id' => $venta->id, 'payment_method_id' => $metodo->id, 'kind' => 'sale', 'amount' => 999, 'paid_at' => now()]);
        Expense::create(['company_id' => $ajena->id, 'concept' => 'X', 'amount' => 999, 'spent_at' => now()]);
        Product::factory()->create(['company_id' => $ajena->id, 'purchase_price' => 999]);

        $this->assertSame(['income' => 0.0, 'cost' => 0.0, 'expenses' => 0.0, 'profit' => 0.0], $this->totals());

        $this->as()->get(route('reports.index', ['section' => 'finanzas']))->assertOk()->assertDontSee('999.00');
    }

    public function test_el_nombre_de_un_producto_no_se_ejecuta(): void
    {
        $product = $this->product(attributes: ['name' => '<script>alert(1)</script>']);
        $this->sale([['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 1, 'unit_price' => 50]]);

        foreach (['resumen', 'ventas'] as $section) {
            $this->as()->get(route('reports.index', ['section' => $section]))->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_excel_es_un_libro_valido_con_numeros_de_verdad(): void
    {
        $this->sale([$this->line(1234.5, ['description' => '=HYPERLINK("http://malo")'])]);

        $response = $this->as()->get(route('reports.export', ['section' => 'ventas', 'preset' => 'this_month']));
        $response->assertOk();
        $this->assertStringContainsString('reporte-ventas-2026-11-01_2026-11-20.xlsx', $response->headers->get('Content-Disposition'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));

        $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
        $this->assertNotFalse($workbook);
        $this->assertGreaterThan(3, $workbook->sheets->sheet->count());

        $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
        $this->assertNotFalse(simplexml_load_string($sheet));
        $this->assertStringContainsString('<v>1234.5</v>', $sheet);

        // Ninguna celda es una fórmula: un concepto con "=" sigue siendo texto.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->assertStringNotContainsString('<f>', (string) $zip->getFromIndex($i));
        }
        $zip->close();
    }

    public function test_todas_las_pantallas_y_exportaciones_responden(): void
    {
        $this->sale([$this->line(100)]);

        foreach (['resumen', 'finanzas', 'ventas', 'clinica', 'inventario'] as $section) {
            foreach (['this_month', 'last_year', 'all'] as $preset) {
                $this->as()->get(route('reports.index', ['section' => $section, 'preset' => $preset]))->assertOk();
            }
            $this->as()->get(route('reports.export', ['section' => $section]))->assertOk();
        }

        $this->as()->get(route('reports.index', ['section' => 'resumen']))->assertSee('Utilidad estimada')->assertSee('Lo más importante');
        $this->as()->get(route('reports.index'))->assertRedirect(route('reports.index', ['section' => 'resumen']));
    }

    public function test_pantallas_vacias_lo_dicen_con_claridad(): void
    {
        $this->as()->get(route('reports.index', ['section' => 'resumen']))->assertOk()->assertSee('Todavía no hay ventas ni cobros');
        $this->as()->get(route('reports.index', ['section' => 'clinica']))->assertOk()->assertSee('Sin consultas finalizadas en este período');
    }

    public function test_el_menu_muestra_reportes_solo_a_quien_corresponde(): void
    {
        $this->as()->get(route('dashboard'))->assertOk()->assertSee(route('reports.index'));

        $sin = $this->userInCompany($this->company, ['sales.view'], 'sin-reportes');
        $this->actingInCompany($sin, $this->company)->get(route('dashboard'))->assertOk()->assertDontSee(route('reports.index'));
    }
}
