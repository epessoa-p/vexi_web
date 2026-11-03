<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Cash;
use App\Support\ClinicalCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Compras: totales con descuento e impuesto, recepción parcial o total con
 * lotes, cierre con diferencias, pagos (desde caja o no) y anulación.
 */
class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
        'purchases.view', 'purchases.create', 'purchases.edit', 'products.view', 'cash.view', 'cash.create',
    ];

    private Company $company;

    private User $user;

    private Branch $branch;

    private Supplier $supplier;

    private PaymentMethod $cash;

    private PaymentMethod $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['compras', 'inventario', 'caja']]);
        $this->user = $this->userInCompany($this->company, self::ALL, 'compras');
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->supplier = Supplier::factory()->create(['company_id' => $this->company->id, 'name' => 'VetSur']);

        app(Tenancy::class)->runFor($this->company->id, fn () => ClinicalCatalog::seedFor($this->company));
        $this->cash = PaymentMethod::allCompanies()->where('company_id', $this->company->id)->where('type', 'cash')->firstOrFail();
        $this->transfer = PaymentMethod::allCompanies()->where('company_id', $this->company->id)->where('type', 'transfer')->firstOrFail();
    }

    private function as()
    {
        return $this->actingInCompany($this->user, $this->company);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['company_id' => $this->company->id, 'purchase_price' => 1] + $attributes);
    }

    private function buy(array $data)
    {
        return $this->as()->post(route('purchases.store'), $data + [
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'purchased_at' => now()->toDateString(),
        ]);
    }

    private function stock(Product $product): float
    {
        return (float) ProductStock::allCompanies()->where('product_id', $product->id)->sum('quantity');
    }

    private function lastPurchase(): Purchase
    {
        return Purchase::allCompanies()->latest('id')->firstOrFail();
    }

    public function test_sin_el_modulo_compras_no_entra(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['inventario']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('purchases.index'))->assertForbidden();
        $this->actingInCompany($user, $sin)->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_compra_recibida_en_el_acto_entra_al_stock_con_su_lote_y_costo(): void
    {
        $vacuna = $this->product(['track_batches' => true]);
        $gasa = $this->product();

        $this->buy([
            'receive_now' => 1,
            'discount' => 10,
            'tax_rate' => 13,
            'items' => [
                ['product_id' => $vacuna->id, 'quantity' => 10, 'unit_cost' => 20, 'discount' => 20, 'lot' => 'VX-1', 'expires_at' => now()->addYear()->toDateString()],
                ['product_id' => $gasa->id, 'quantity' => 50, 'unit_cost' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $purchase = $this->lastPurchase();
        // (180 + 50 − 10) × 1,13 = 248,60
        $this->assertSame('230.00', $purchase->subtotal);
        $this->assertSame('28.60', $purchase->tax);
        $this->assertSame('248.60', $purchase->total);
        $this->assertSame('248.60', $purchase->balance);
        $this->assertSame('received', $purchase->status);

        $this->assertSame(10.0, $this->stock($vacuna));
        $this->assertSame('VX-1', ProductBatch::allCompanies()->where('product_id', $vacuna->id)->value('lot'));
        // El costo de compra pasa al producto: 180 / 10 = 18 (con el descuento de la línea).
        $this->assertEquals(18, $vacuna->fresh()->purchase_price);
        $this->assertSame('purchase', StockMovement::allCompanies()->where('product_id', $gasa->id)->value('reason'));
    }

    public function test_un_producto_con_lotes_no_se_recibe_sin_lote_y_no_queda_nada_a_medias(): void
    {
        $vacuna = $this->product(['track_batches' => true]);

        $this->buy(['receive_now' => 1, 'items' => [['product_id' => $vacuna->id, 'quantity' => 5, 'unit_cost' => 10]]])
            ->assertSessionHasErrors('lot');

        $this->assertSame(0, Purchase::allCompanies()->count());
        $this->assertSame(0.0, $this->stock($vacuna));
    }

    public function test_pedido_con_recepcion_parcial_y_cierre_con_diferencias(): void
    {
        $product = $this->product();
        $this->buy(['items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 10]]])->assertSessionHasNoErrors();
        $purchase = $this->lastPurchase();
        $item = $purchase->items()->allCompanies()->firstOrFail();
        $this->assertSame('ordered', $purchase->status);
        $this->assertSame(0.0, $this->stock($product));

        // No se recibe más de lo pendiente.
        $this->as()->post(route('purchases.receive', $purchase), ['items' => [$item->id => ['quantity' => 12]]])->assertSessionHasErrors('items');

        $this->as()->post(route('purchases.receive', $purchase), ['items' => [$item->id => ['quantity' => 6]]])->assertSessionHasNoErrors();
        $this->assertSame('partial', $purchase->fresh()->status);
        $this->assertSame(6.0, $this->stock($product));

        // El proveedor no tiene el resto: se cierra y se debe solo lo recibido.
        $this->as()->post(route('purchases.close', $purchase), ['closing_notes' => 'Sin stock en el proveedor'])->assertSessionHasNoErrors();
        $purchase->refresh();
        $this->assertSame('received', $purchase->status);
        $this->assertSame('60.00', $purchase->total);
        $this->assertSame('60.00', $purchase->balance);
    }

    public function test_pagos_al_proveedor_desde_caja_y_por_banco(): void
    {
        $caja = Caja::create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Caja', 'active' => true]);
        $this->as()->post(route('cash.open', $caja), ['opening_amount' => 500]);
        $session = CashSession::allCompanies()->firstOrFail();
        $product = $this->product();

        // No se paga más que el total.
        $this->buy(['items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 30]], 'paid_amount' => 400,
            'payment_method_id' => $this->cash->id])->assertSessionHasErrors('payments');

        $this->buy(['items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 30]], 'paid_amount' => 100,
            'payment_method_id' => $this->cash->id, 'cash_session_id' => $session->id])->assertSessionHasNoErrors();
        $purchase = $this->lastPurchase();
        $this->assertSame('200.00', $purchase->balance);
        $this->assertSame(400.0, $session->fresh()->expectedCash());

        // La transferencia exige referencia y no sale de la caja.
        $this->as()->post(route('purchases.payments.store', $purchase), ['payment_method_id' => $this->transfer->id, 'amount' => 50])
            ->assertSessionHasErrors('reference');
        $this->as()->post(route('purchases.payments.store', $purchase), ['payment_method_id' => $this->transfer->id, 'amount' => 300, 'reference' => 'X'])
            ->assertSessionHasErrors('amount');
        $this->as()->post(route('purchases.payments.store', $purchase), ['payment_method_id' => $this->transfer->id, 'amount' => 200, 'reference' => 'TRX'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', $purchase->fresh()->balance);
        $this->assertSame(400.0, $session->fresh()->expectedCash());
        // Pagada del todo: ya no hay deuda (la compra solo aparece en el historial de pagos).
        $this->as()->get(route('payables.index'))->assertOk()->assertSee('No hay deudas con proveedores');
    }

    public function test_solo_se_anula_un_pedido_sin_recepciones_ni_pagos(): void
    {
        $product = $this->product();
        $this->buy(['items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 10]]]);
        $pedido = $this->lastPurchase();
        $this->as()->post(route('purchases.cancel', $pedido), ['cancel_reason' => 'Duplicado'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $pedido->fresh()->status);
        $this->assertSame('0.00', $pedido->fresh()->balance);

        $this->buy(['receive_now' => 1, 'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 10]]]);
        $recibida = $this->lastPurchase();
        $this->as()->post(route('purchases.cancel', $recibida), ['cancel_reason' => 'X'])->assertSessionHasErrors('cancel_reason');
        $this->assertSame(5.0, $this->stock($product));
    }

    public function test_no_cruza_veterinarias(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['compras', 'inventario']]);
        $proveedorAjeno = Supplier::factory()->create(['company_id' => $ajena->id]);
        $product = $this->product();

        $this->as()->post(route('purchases.store'), ['supplier_id' => $proveedorAjeno->id, 'purchased_at' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1]]])->assertSessionHasErrors('supplier_id');
        $this->as()->get(route('suppliers.show', $proveedorAjeno))->assertNotFound();
    }

    public function test_las_pantallas_cargan(): void
    {
        $product = $this->product(['name' => 'Gasas']);
        $this->buy(['items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 10]], 'due_date' => now()->addWeek()->toDateString()]);
        $purchase = $this->lastPurchase();

        $as = $this->as();
        $as->get(route('purchases.index'))->assertOk()->assertSee($purchase->code);
        $as->get(route('purchases.create', ['supplier_id' => $this->supplier->id]))->assertOk();
        $as->get(route('purchases.show', $purchase))->assertOk()->assertSee('Registrar recepción')->assertSee('Gasas');
        $as->get(route('payables.index'))->assertOk()->assertSee('VetSur');
        $as->get(route('suppliers.index'))->assertOk();
        $as->get(route('suppliers.show', $this->supplier))->assertOk()->assertSee($purchase->code);
        $as->get(route('suppliers.create'))->assertOk();
        $as->get(route('suppliers.edit', $this->supplier))->assertOk();
    }
}
