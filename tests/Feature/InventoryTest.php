<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventario: kardex con saldo, lotes y FEFO, ajustes, transferencias,
 * límite de plan y los descuentos que disparan los módulos clínicos.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'products.view', 'products.create', 'products.edit', 'products.delete',
        'inventory.view', 'inventory.create', 'inventory.edit',
        'consultations.view', 'consultations.create', 'consultations.edit',
        'vaccinations.view', 'vaccinations.create', 'vaccinations.edit', 'vaccinations.delete', 'pets.view',
    ];

    private Company $company;

    private User $user;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['inventario', 'consultas', 'vacunas']]);
        $this->user = $this->userInCompany($this->company, self::ALL, 'encargado');
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'Central']);
    }

    private function as()
    {
        return $this->actingInCompany($this->user, $this->company);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['company_id' => $this->company->id] + $attributes);
    }

    private function move(array $data)
    {
        return $this->as()->post(route('stock-movements.store'), $data + ['branch_id' => $this->branch->id]);
    }

    private function stock(Product $product, ?Branch $branch = null): float
    {
        return (float) ProductStock::allCompanies()->where('product_id', $product->id)
            ->where('branch_id', ($branch ?? $this->branch)->id)->value('quantity');
    }

    /** Ejecuta con la veterinaria activa, como dentro de una petición. */
    private function inTenant(callable $callback): mixed
    {
        return app(Tenancy::class)->runFor($this->company->id, $callback);
    }

    public function test_sin_el_modulo_inventario_no_entra(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('products.index'))->assertForbidden();
        $this->actingInCompany($user, $sin)->get(route('stock-movements.index'))->assertForbidden();
    }

    public function test_respeta_el_limite_de_productos_del_plan(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => ['inventario'], 'max_products' => 1]);
        $user = $this->userInCompany($company, self::ALL, 'limitado');
        Product::factory()->create(['company_id' => $company->id]);

        $this->actingInCompany($user, $company)->post(route('products.store'), [
            'name' => 'Otro', 'unit' => 'unidad', 'purchase_price' => 1, 'sale_price' => 2, 'min_stock' => 0,
        ])->assertSessionHasErrors('error');

        $this->assertSame(1, Product::allCompanies()->where('company_id', $company->id)->count());
    }

    public function test_entrada_con_lote_deja_kardex_con_saldo(): void
    {
        $product = $this->product(['track_batches' => true]);

        // Un producto con lotes no entra sin lote.
        $this->move(['operation' => 'in', 'product_id' => $product->id, 'quantity' => 5])->assertSessionHasErrors('lot');

        $this->move(['operation' => 'in', 'product_id' => $product->id, 'quantity' => 10, 'lot' => 'L1',
            'expires_at' => now()->addYear()->toDateString(), 'reason' => 'purchase', 'unit_cost' => 7.5])->assertSessionHasNoErrors();
        $this->move(['operation' => 'in', 'product_id' => $product->id, 'quantity' => 5, 'lot' => 'L1']);

        $this->assertSame(15.0, $this->stock($product));
        $this->assertSame(15.0, (float) ProductBatch::allCompanies()->where('lot', 'L1')->value('quantity'));
        $this->assertSame(['10.00', '15.00'], StockMovement::allCompanies()->orderBy('id')->pluck('balance_after')->all());
        $this->assertEquals(7.5, $product->fresh()->purchase_price);
    }

    public function test_salida_consume_primero_lo_que_vence_antes_y_salta_lo_vencido(): void
    {
        $product = $this->product(['track_batches' => true]);
        $this->inTenant(function () use ($product) {
            Stock::receive($product, $this->branch, 5, 'initial', ['lot' => 'VENCIDO', 'expires_at' => now()->subDay()]);
            Stock::receive($product, $this->branch, 4, 'initial', ['lot' => 'TARDE', 'expires_at' => now()->addYear()]);
            Stock::receive($product, $this->branch, 3, 'initial', ['lot' => 'PRONTO', 'expires_at' => now()->addMonth()]);
        });

        $this->move(['operation' => 'out', 'product_id' => $product->id, 'quantity' => 5, 'reason' => 'internal_use'])->assertSessionHasNoErrors();

        // Sale el que vence antes (PRONTO: 3), luego TARDE (2); el vencido no se toca.
        $lots = ProductBatch::allCompanies()->pluck('quantity', 'lot');
        $this->assertSame('0.00', $lots['PRONTO']);
        $this->assertSame('2.00', $lots['TARDE']);
        $this->assertSame('5.00', $lots['VENCIDO']);

        // Pedir más de lo disponible (sin contar lo vencido) se rechaza y no mueve nada.
        $this->move(['operation' => 'out', 'product_id' => $product->id, 'quantity' => 3])->assertSessionHasErrors('quantity');
        $this->assertSame(7.0, $this->stock($product));
    }

    public function test_ajuste_registra_la_diferencia_contra_lo_contado(): void
    {
        $product = $this->product();
        $this->inTenant(fn () => Stock::receive($product, $this->branch, 10, 'initial'));

        $this->move(['operation' => 'adjustment', 'product_id' => $product->id, 'quantity' => 8, 'notes' => 'Conteo mensual'])->assertSessionHasNoErrors();

        $this->assertSame(8.0, $this->stock($product));
        $adjustment = StockMovement::allCompanies()->where('type', 'adjustment')->firstOrFail();
        $this->assertSame('-2.00', $adjustment->quantity);
        $this->assertSame('count', $adjustment->reason);
    }

    public function test_transferencia_conserva_el_lote(): void
    {
        $other = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'Norte']);
        $product = $this->product(['track_batches' => true]);
        $this->inTenant(fn () => Stock::receive($product, $this->branch, 10, 'initial', ['lot' => 'T1', 'expires_at' => now()->addYear()]));

        $this->move(['operation' => 'transfer', 'product_id' => $product->id, 'to_branch_id' => $other->id, 'quantity' => 4])->assertSessionHasNoErrors();

        $this->assertSame(6.0, $this->stock($product));
        $this->assertSame(4.0, $this->stock($product, $other));
        $moved = ProductBatch::allCompanies()->where('branch_id', $other->id)->firstOrFail();
        $this->assertSame('T1', $moved->lot);
        $this->assertNotNull($moved->expires_at);
    }

    public function test_baja_de_lote_vencido(): void
    {
        $product = $this->product(['track_batches' => true]);
        $this->inTenant(fn () => Stock::receive($product, $this->branch, 6, 'initial', ['lot' => 'OLD', 'expires_at' => now()->subWeek()]));
        $batch = ProductBatch::allCompanies()->firstOrFail();

        $this->as()->get(route('expiry.index', ['tab' => 'expired']))->assertOk()->assertSee('OLD');
        $this->as()->post(route('expiry.write-off', $batch))->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $batch->fresh()->quantity);
        $this->assertSame('expired', StockMovement::allCompanies()->latest('id')->value('reason'));
    }

    public function test_la_consulta_descuenta_insumos_al_finalizar_y_dispensa_la_receta(): void
    {
        $jeringa = $this->product(['name' => 'Jeringa']);
        $amoxi = $this->product(['name' => 'Amoxicilina']);
        $this->inTenant(function () use ($jeringa, $amoxi) {
            Stock::receive($jeringa, $this->branch, 1, 'initial');
            Stock::receive($amoxi, $this->branch, 20, 'initial');
        });
        $pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);
        $consultation = Consultation::factory()->create(['pet_id' => $pet->id, 'branch_id' => $this->branch->id]);

        $this->as()->put(route('consultations.update', $consultation), [
            'finish' => 1,
            'diagnoses' => [['type' => 'primary', 'description' => 'Herida']],
            'prescription' => [['medication' => 'Amoxicilina', 'product_id' => $amoxi->id, 'dose' => '1 comp.', 'route' => 'oral', 'frequency' => 'cada 12 h']],
            // Se usaron 2 jeringas y solo había 1: se registra igual y queda en negativo.
            'supplies' => [['product_id' => $jeringa->id, 'quantity' => 2], ['product_id' => '', 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(-1.0, $this->stock($jeringa));

        $item = $consultation->prescriptionItems()->allCompanies()->firstOrFail();
        $this->as()->post(route('consultations.dispense', [$consultation, $item]), ['quantity' => 14])->assertSessionHasNoErrors();

        $this->assertSame(6.0, $this->stock($amoxi));
        $this->assertNotNull($item->fresh()->dispensed_at);
        // No se dispensa dos veces.
        $this->as()->post(route('consultations.dispense', [$consultation, $item]), ['quantity' => 1])->assertSessionHasErrors('error');
    }

    public function test_la_vacuna_vinculada_descuenta_su_lote_y_se_revierte_al_borrarla(): void
    {
        $product = $this->product(['name' => 'Vacuna rabia', 'track_batches' => true]);
        $this->inTenant(fn () => Stock::receive($product, $this->branch, 10, 'initial', ['lot' => 'RB-1', 'expires_at' => now()->addYear()]));
        $vaccine = Vaccine::factory()->create(['company_id' => $this->company->id, 'product_id' => $product->id]);
        $pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);

        $this->as()->post(route('vaccinations.store'), [
            'pet_id' => $pet->id, 'vaccine_id' => $vaccine->id, 'applied_at' => now()->toDateString(), 'dose_number' => 1,
        ])->assertSessionHasNoErrors();

        $vaccination = Vaccination::allCompanies()->firstOrFail();
        $this->assertSame(9.0, $this->stock($product));
        // Sin lote anotado, la aplicación toma el del frasco que salió del stock.
        $this->assertSame('RB-1', $vaccination->lot);

        $this->as()->delete(route('vaccinations.destroy', $vaccination))->assertSessionHasNoErrors();
        $this->assertSame(10.0, $this->stock($product));
        $this->assertSame(10.0, (float) ProductBatch::allCompanies()->value('quantity'));
        $this->assertSame('reversal', StockMovement::allCompanies()->latest('id')->value('reason'));
    }

    public function test_no_cruza_veterinarias(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['inventario']]);
        $productoAjeno = Product::factory()->create(['company_id' => $ajena->id, 'name' => 'Ajeno']);
        $this->product(['name' => 'Propio']);

        $this->as()->get(route('products.show', $productoAjeno))->assertNotFound();
        $this->as()->getJson(route('products.search', ['q' => 'o']))->assertOk()->assertJsonMissing(['name' => 'Ajeno']);
        $this->move(['operation' => 'in', 'product_id' => $productoAjeno->id, 'quantity' => 1])->assertSessionHasErrors('product_id');
    }

    public function test_las_pantallas_cargan(): void
    {
        $product = $this->product(['track_batches' => true, 'min_stock' => 5]);
        $this->inTenant(fn () => Stock::receive($product, $this->branch, 2, 'initial', ['lot' => 'X', 'expires_at' => now()->addDays(10)]));

        $as = $this->as();
        $as->get(route('products.index', ['status' => 'low']))->assertOk()->assertSee($product->name);
        $as->get(route('products.create'))->assertOk();
        $as->get(route('products.show', $product))->assertOk()->assertSee('Kardex');
        $as->get(route('products.edit', $product))->assertOk();
        $as->get(route('stock-movements.index'))->assertOk();
        foreach (['in', 'out', 'adjustment'] as $operation) {
            $as->get(route('stock-movements.create', ['operation' => $operation, 'product_id' => $product->id]))->assertOk();
        }
        $as->get(route('expiry.index'))->assertOk()->assertSee($product->name);
        $as->get(route('product-categories.index'))->assertOk();
        $as->get(route('dashboard'))->assertOk()->assertSee('Alertas de inventario');
    }
}
