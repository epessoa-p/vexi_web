<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Support\Cash;
use App\Support\ClinicalCatalog;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ventas y caja: el POS deja coherentes stock, caja y saldo; crédito y
 * abonos; devoluciones y anulación; cobro de consultas; arqueo y gastos.
 */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'sales.view', 'sales.create', 'sales.edit', 'cash.view', 'cash.create',
        'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',
        'products.view', 'clients.view', 'pets.view', 'consultations.view',
    ];

    private Company $company;

    private User $user;

    private Branch $branch;

    private Caja $caja;

    private PaymentMethod $cash;

    private PaymentMethod $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['pos', 'caja', 'inventario', 'consultas']]);
        $this->user = $this->userInCompany($this->company, self::ALL, 'cajero');
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->caja = Caja::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Caja 1', 'active' => true]);

        $this->inTenant(fn () => ClinicalCatalog::seedFor($this->company));
        $this->cash = PaymentMethod::allCompanies()->where('company_id', $this->company->id)->where('type', 'cash')->firstOrFail();
        $this->transfer = PaymentMethod::allCompanies()->where('company_id', $this->company->id)->where('type', 'transfer')->firstOrFail();
    }

    private function as()
    {
        return $this->actingInCompany($this->user, $this->company);
    }

    private function inTenant(callable $callback): mixed
    {
        return app(Tenancy::class)->runFor($this->company->id, $callback);
    }

    private function openCash(float $amount = 100): CashSession
    {
        $this->as()->post(route('cash.open', $this->caja), ['opening_amount' => $amount])->assertSessionHasNoErrors();

        return CashSession::allCompanies()->where('status', 'open')->firstOrFail();
    }

    private function product(float $stock, array $attributes = []): Product
    {
        $product = Product::factory()->create(['company_id' => $this->company->id, 'sale_price' => 50] + $attributes);
        if ($stock > 0) {
            $this->inTenant(fn () => Stock::receive($product, $this->branch, $stock, 'initial',
                $product->track_batches ? ['lot' => 'L1', 'expires_at' => now()->addYear()] : []));
        }

        return $product;
    }

    private function stock(Product $product): float
    {
        return (float) ProductStock::allCompanies()->where('product_id', $product->id)->sum('quantity');
    }

    private function sell(array $data)
    {
        return $this->as()->post(route('pos.store'), $data);
    }

    private function lastSale(): Sale
    {
        return Sale::allCompanies()->latest('id')->firstOrFail();
    }

    public function test_sin_el_modulo_de_ventas_no_entra_al_pos(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('pos.create'))->assertForbidden();
        $this->actingInCompany($user, $sin)->get(route('cash.index'))->assertForbidden();
    }

    public function test_con_caja_no_se_vende_sin_un_turno_abierto(): void
    {
        $this->sell([
            'items' => [['type' => 'other', 'description' => 'Corte de uñas', 'quantity' => 1, 'unit_price' => 30]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 30]],
        ])->assertSessionHasErrors('cash_session_id');

        $this->assertSame(0, Sale::allCompanies()->count());
    }

    public function test_venta_al_contado_descuenta_stock_y_da_vuelto(): void
    {
        $session = $this->openCash(100);
        $product = $this->product(10, ['track_batches' => true]);

        $this->sell([
            'cash_session_id' => $session->id,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'description' => 'Producto', 'quantity' => 3, 'unit_price' => 50, 'discount' => 10]],
            'discount' => 5,
            // Total 135: paga 100 por transferencia y entrega 50 en efectivo → vuelto 15.
            'payments' => [
                ['payment_method_id' => $this->transfer->id, 'amount' => 100, 'reference' => 'TRX-1'],
                ['payment_method_id' => $this->cash->id, 'amount' => 50],
            ],
        ])->assertSessionHasNoErrors();

        $sale = $this->lastSale();
        $this->assertSame(1, $sale->number);
        $this->assertSame('135.00', $sale->total);
        $this->assertSame('135.00', $sale->paid);
        $this->assertSame('0.00', $sale->balance);
        $this->assertSame(7.0, $this->stock($product));
        $this->assertEqualsCanonicalizing(['100.00', '35.00'], $sale->payments()->allCompanies()->pluck('amount')->all());
        // En la caja solo cuenta como efectivo lo que quedó (35), más el saldo inicial.
        $this->assertSame(135.0, $session->fresh()->expectedCash());

        $this->as()->get(route('sales.receipt', $sale))->assertOk()->assertSee('V-000001')->assertSee('Transferencia');
    }

    public function test_el_vuelto_solo_sale_del_efectivo_y_la_transferencia_exige_referencia(): void
    {
        $session = $this->openCash();
        $base = ['cash_session_id' => $session->id, 'items' => [['type' => 'other', 'description' => 'X', 'quantity' => 1, 'unit_price' => 40]]];

        $this->sell($base + ['payments' => [['payment_method_id' => $this->transfer->id, 'amount' => 50, 'reference' => 'R']]])
            ->assertSessionHasErrors('payments');
        $this->sell($base + ['payments' => [['payment_method_id' => $this->transfer->id, 'amount' => 40]]])
            ->assertSessionHasErrors('payments');

        $this->assertSame(0, Sale::allCompanies()->count());
    }

    public function test_no_vende_lo_que_no_hay_y_no_deja_nada_a_medias(): void
    {
        $session = $this->openCash();
        $product = $this->product(2);

        $this->sell([
            'cash_session_id' => $session->id,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 5, 'unit_price' => 50]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 250]],
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, Sale::allCompanies()->count());
        $this->assertSame(2.0, $this->stock($product));
        $this->assertSame(0, $session->movements()->allCompanies()->count());
    }

    public function test_venta_a_credito_y_abonos(): void
    {
        $session = $this->openCash();
        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $credit = ['cash_session_id' => $session->id, 'items' => [['type' => 'other', 'description' => 'Cirugía', 'quantity' => 1, 'unit_price' => 500]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 200]]];

        // Sin cliente no hay a quién cobrarle el saldo.
        $this->sell($credit)->assertSessionHasErrors('payments');

        $this->sell($credit + ['client_id' => $client->id, 'due_date' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $sale = $this->lastSale();
        $this->assertSame('300.00', $sale->balance);

        $this->as()->get(route('receivables.index'))->assertOk()->assertSee($client->full_name);

        // No se abona más que el saldo.
        $this->as()->post(route('sales.payments.store', $sale), ['payment_method_id' => $this->cash->id, 'amount' => 400, 'cash_session_id' => $session->id])
            ->assertSessionHasErrors('amount');
        $this->as()->post(route('sales.payments.store', $sale), ['payment_method_id' => $this->cash->id, 'amount' => 300, 'cash_session_id' => $session->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $sale->fresh()->balance);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame(600.0, $session->fresh()->expectedCash());
    }

    public function test_devolucion_parcial_reingresa_al_mismo_lote_y_reembolsa(): void
    {
        $session = $this->openCash(0);
        $product = $this->product(10, ['track_batches' => true]);
        $this->sell(['cash_session_id' => $session->id,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 4, 'unit_price' => 50]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 200]]]);
        $sale = $this->lastSale();
        $item = $sale->items()->allCompanies()->firstOrFail();

        $this->as()->post(route('sales.return', $sale), [
            'quantities' => [$item->id => 5], 'payment_method_id' => $this->cash->id, 'cash_session_id' => $session->id,
        ])->assertSessionHasErrors('items');

        $this->as()->post(route('sales.return', $sale), [
            'quantities' => [$item->id => 1], 'payment_method_id' => $this->cash->id, 'cash_session_id' => $session->id, 'reason' => 'Fallado',
        ])->assertSessionHasNoErrors();

        $this->assertSame(7.0, $this->stock($product));
        $this->assertSame('7.00', \App\Models\ProductBatch::allCompanies()->where('lot', 'L1')->value('quantity'));
        $this->assertSame('50.00', $sale->fresh()->refunded);
        $this->assertSame(150.0, $session->fresh()->expectedCash());
    }

    public function test_la_devolucion_de_una_venta_a_credito_primero_baja_el_saldo(): void
    {
        $session = $this->openCash(0);
        $client = Client::factory()->create(['company_id' => $this->company->id]);
        $product = $this->product(5);
        $this->sell(['cash_session_id' => $session->id, 'client_id' => $client->id,
            'items' => [['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 2, 'unit_price' => 50]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 30]]]);
        $sale = $this->lastSale();

        // Devuelve 1 (50): baja el saldo de 70 a 20 y no hay reembolso ni caja que elegir.
        $this->as()->post(route('sales.return', $sale), ['quantities' => [$sale->items()->allCompanies()->first()->id => 1]])
            ->assertSessionHasNoErrors();

        $this->assertSame('20.00', $sale->fresh()->balance);
        $this->assertSame('0.00', $sale->fresh()->refunded);
    }

    public function test_anular_devuelve_stock_reembolsa_y_libera_la_consulta(): void
    {
        $session = $this->openCash(0);
        $product = $this->product(5);
        $pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);
        $consultation = Consultation::factory()->finished()->create(['pet_id' => $pet->id]);

        $this->sell(['cash_session_id' => $session->id, 'client_id' => $pet->client_id, 'pet_id' => $pet->id,
            'items' => [
                ['type' => 'product', 'product_id' => $product->id, 'description' => 'P', 'quantity' => 2, 'unit_price' => 50],
                ['type' => 'consultation', 'consultation_id' => $consultation->id, 'description' => 'Consulta', 'quantity' => 1, 'unit_price' => 120],
            ],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 220]]])->assertSessionHasNoErrors();
        $sale = $this->lastSale();
        $this->assertSame($sale->id, $consultation->fresh()->sale_id);

        $this->as()->post(route('sales.cancel', $sale), ['cancel_reason' => 'Error de carga'])->assertSessionHasErrors();
        $this->as()->post(route('sales.cancel', $sale), [
            'cancel_reason' => 'Error de carga', 'payment_method_id' => $this->cash->id, 'cash_session_id' => $session->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame(5.0, $this->stock($product));
        $this->assertNull($consultation->fresh()->sale_id);
        $this->assertSame(0.0, $session->fresh()->expectedCash());
    }

    public function test_cobro_de_consultas_sin_descontar_dos_veces(): void
    {
        $session = $this->openCash();
        $jeringa = $this->product(10);
        $pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);
        $consultation = Consultation::factory()->finished()->create(['pet_id' => $pet->id]);
        $consultation->supplies()->create(['company_id' => $this->company->id, 'product_id' => $jeringa->id, 'quantity' => 2]);
        $consultation->procedures()->create(['company_id' => $this->company->id, 'name' => 'Sutura', 'cost' => 80]);
        // Los insumos ya salieron del stock al finalizar la consulta.
        $this->inTenant(fn () => Stock::consume($jeringa, $this->branch, 2, 'procedure', ['source' => $consultation]));

        $lines = $this->as()->getJson(route('pos.billable', $pet))->assertOk()->assertJsonCount(1)->json('0.lines');
        $this->assertCount(3, $lines);   // consulta + procedimiento + insumo

        $this->sell(['cash_session_id' => $session->id, 'client_id' => $pet->client_id, 'pet_id' => $pet->id,
            'items' => $lines, 'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 1000]]])->assertSessionHasNoErrors();

        $this->assertSame(8.0, $this->stock($jeringa));
        $this->assertNotNull($consultation->fresh()->sale_id);
        $this->as()->getJson(route('pos.billable', $pet))->assertJsonCount(0);

        // Volver a cobrarla se rechaza.
        $this->sell(['cash_session_id' => $session->id, 'client_id' => $pet->client_id,
            'items' => [['type' => 'consultation', 'consultation_id' => $consultation->id, 'description' => 'Consulta', 'quantity' => 1, 'unit_price' => 10]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 10]]])->assertSessionHasErrors('items');
    }

    public function test_arqueo_y_cierre_con_gasto_desde_caja(): void
    {
        $session = $this->openCash(100);
        $this->as()->post(route('cash.movement', $session), ['type' => 'withdrawal', 'payment_method_id' => $this->cash->id, 'amount' => 20, 'description' => 'Depósito'])
            ->assertSessionHasNoErrors();
        $this->as()->post(route('expenses.store'), ['concept' => 'Gasas', 'amount' => 30, 'spent_at' => now()->toDateString(),
            'payment_method_id' => $this->cash->id, 'cash_session_id' => $session->id])->assertSessionHasNoErrors();

        $this->assertSame(50.0, $session->fresh()->expectedCash());

        $expense = Expense::allCompanies()->firstOrFail();
        $this->as()->delete(route('expenses.destroy', $expense))->assertSessionHasNoErrors();
        $this->assertSame(80.0, $session->fresh()->expectedCash());

        $this->as()->post(route('cash.close', $session), ['counted_cash' => 75])->assertSessionHasNoErrors();
        $closed = $session->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertSame('-5.00', $closed->difference);

        // Con la caja cerrada ya no se registran movimientos.
        $this->as()->post(route('cash.movement', $session), ['type' => 'income', 'payment_method_id' => $this->cash->id, 'amount' => 5, 'description' => 'X'])
            ->assertSessionHasErrors('cash_session_id');
    }

    public function test_numeracion_por_veterinaria_y_aislamiento(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['pos']]);
        $ventaAjena = Sale::create(['company_id' => $ajena->id, 'number' => 1, 'total' => 10, 'subtotal' => 10]);

        $session = $this->openCash();
        $this->sell(['cash_session_id' => $session->id, 'items' => [['type' => 'other', 'description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 10]]]);

        $this->assertSame(1, $this->lastSale()->number);   // la otra veterinaria también tiene su V-000001
        $this->as()->get(route('sales.show', $ventaAjena))->assertNotFound();
    }

    public function test_las_pantallas_cargan(): void
    {
        $session = $this->openCash();
        $this->sell(['cash_session_id' => $session->id, 'items' => [['type' => 'other', 'description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 10]]]);

        $as = $this->as();
        $as->get(route('pos.create'))->assertOk()->assertSee('Registrar venta');
        $as->get(route('sales.index'))->assertOk()->assertSee('V-000001');
        $as->get(route('sales.show', $this->lastSale()))->assertOk();
        $as->get(route('cash.index'))->assertOk()->assertSee('Caja 1');
        $as->get(route('cash.show', $session))->assertOk();
        $as->get(route('expenses.index'))->assertOk();
        $as->get(route('expenses.create'))->assertOk();
        $as->get(route('receivables.index'))->assertOk();
        $as->get(route('payment-methods.index'))->assertOk();
        $as->get(route('expense-categories.index'))->assertOk();
        $as->get(route('dashboard'))->assertOk()->assertSee('Indicadores económicos');
    }
}
