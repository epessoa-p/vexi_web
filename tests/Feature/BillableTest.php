<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Hospitalization;
use App\Models\LabRequest;
use App\Models\LabStudyType;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\User;
use App\Support\ClinicalCatalog;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estudios e internaciones llegan al punto de venta como pendientes de cobro,
 * se cobran una sola vez y anular la venta los deja otra vez pendientes.
 */
class BillableTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'sales.view', 'sales.create', 'sales.edit',
        'lab_requests.view', 'lab_requests.edit', 'lab_studies.view', 'lab_studies.edit',
        'hospitalizations.view', 'hospitalizations.create', 'hospitalizations.edit', 'pets.view',
    ];

    private Company $company;

    private User $user;

    private Branch $branch;

    private PaymentMethod $cash;

    private Pet $pet;

    private LabStudyType $study;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['pos', 'inventario', 'laboratorio', 'hospitalizacion']]);
        $this->user = $this->userInCompany($this->company, self::ALL, 'admin-clinica');
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        app(Tenancy::class)->runFor($this->company->id, fn () => ClinicalCatalog::seedFor($this->company));
        $this->cash = PaymentMethod::allCompanies()->where('company_id', $this->company->id)->where('type', 'cash')->firstOrFail();
        $this->pet = $this->newPet();
        $this->study = LabStudyType::factory()->create(['company_id' => $this->company->id, 'name' => 'Perfil canino', 'price' => 90]);
    }

    private function newPet(): Pet
    {
        return Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);
    }

    private function as()
    {
        return $this->actingInCompany($this->user, $this->company);
    }

    private function labRequest(array $attributes = []): LabRequest
    {
        return LabRequest::factory()->create(['pet_id' => $this->pet->id, 'lab_study_type_id' => $this->study->id] + $attributes);
    }

    private function closedStay(array $attributes = []): Hospitalization
    {
        return Hospitalization::factory()->create($attributes + [
            'pet_id' => $this->pet->id, 'status' => 'discharged', 'daily_rate' => 100,
            'admitted_at' => now()->subHours(50), 'discharged_at' => now()->subHours(2), 'final_diagnosis' => 'Resuelto',
        ]);
    }

    private function billable(?Pet $pet = null): array
    {
        return $this->as()->getJson(route('pos.billable', $pet ?? $this->pet))->assertOk()->json();
    }

    private function sell(array $lines, ?int $clientId = null, array $extra = [])
    {
        $total = collect($lines)->sum(fn ($l) => $l['quantity'] * $l['unit_price']);

        return $this->as()->post(route('pos.store'), $extra + [
            'client_id' => $clientId ?? $this->pet->client_id,
            'pet_id' => $this->pet->id,
            'branch_id' => $this->branch->id,
            'items' => $lines,
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => $total]],
        ]);
    }

    private function lastSale(): Sale
    {
        return Sale::allCompanies()->latest('id')->firstOrFail();
    }

    public function test_el_estudio_se_cobra_una_vez_y_anular_lo_libera(): void
    {
        $request = $this->labRequest(['status' => 'requested']);

        $rows = $this->billable();
        $this->assertCount(1, $rows);
        $this->assertSame("lab_request_id:{$request->id}", $rows[0]['key']);
        $this->assertEquals(90, $rows[0]['lines'][0]['unit_price']);

        $this->sell($rows[0]['lines'])->assertSessionHasNoErrors();
        $sale = $this->lastSale();
        $this->assertSame($sale->id, $request->fresh()->sale_id);
        $this->assertSame('90.00', $sale->total);
        $this->assertSame($request->id, $sale->items()->first()->lab_request_id);
        $this->assertSame([], $this->billable());

        // Volver a cobrarlo se rechaza y no deja una venta a medias.
        $this->sell($rows[0]['lines'])->assertSessionHasErrors('items');
        $this->assertSame(1, Sale::allCompanies()->count());

        $this->as()->post(route('sales.cancel', $sale), ['cancel_reason' => 'Error', 'payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();
        $this->assertNull($request->fresh()->sale_id);
        $this->assertCount(1, $this->billable());
    }

    public function test_un_estudio_anulado_no_se_cobra(): void
    {
        $request = $this->labRequest(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'X']);

        $this->assertSame([], $this->billable());
        $this->sell([['type' => 'lab', 'lab_request_id' => $request->id, 'description' => 'Estudio', 'quantity' => 1, 'unit_price' => 90]])
            ->assertSessionHasErrors('items.0.lab_request_id');
    }

    public function test_anular_un_estudio_cobrado_avisa_de_la_devolucion(): void
    {
        $request = $this->labRequest(['status' => 'requested']);
        $this->sell($this->billable()[0]['lines'])->assertSessionHasNoErrors();

        $this->as()->post(route('lab.transition', [$request, 'cancel']), ['cancel_reason' => 'Muestra dañada'])->assertSessionHasNoErrors();

        $this->assertSame($this->lastSale()->id, $request->fresh()->sale_id);
        $this->as()->get(route('lab.show', $request))->assertOk()->assertSee('ya se había cobrado');
    }

    public function test_la_internacion_activa_no_se_cobra(): void
    {
        $stay = Hospitalization::factory()->create(['pet_id' => $this->pet->id]);

        $this->assertSame([], $this->billable());
        $this->sell([['type' => 'hospitalization', 'hospitalization_id' => $stay->id, 'description' => 'Estadía', 'quantity' => 1, 'unit_price' => 10]])
            ->assertSessionHasErrors('items.0.hospitalization_id');
        $this->assertNull($stay->fresh()->sale_id);
    }

    public function test_la_internacion_sin_costos_no_queda_pendiente(): void
    {
        $this->closedStay(['daily_rate' => 0]);

        $this->assertSame([], $this->billable());
    }

    public function test_la_internacion_cerrada_cobra_estadia_y_cargos_sin_tocar_el_stock(): void
    {
        $product = Product::factory()->create(['company_id' => $this->company->id, 'name' => 'Amoxicilina', 'sale_price' => 10]);
        app(Tenancy::class)->runFor($this->company->id, fn () => Stock::receive($product, $this->branch, 10, 'initial', $product->track_batches ? ['lot' => 'L1', 'expires_at' => now()->addYear()] : []));

        // Se internó, se aplicaron dos tomas (salen del stock) y se agregó un cargo manual.
        $stay = Hospitalization::factory()->create(['pet_id' => $this->pet->id, 'daily_rate' => 100, 'admitted_at' => now()->subHours(50)]);
        $this->as()->post(route('hospitalizations.treatments.store', $stay), [
            'product_id' => $product->id, 'stock_quantity' => 1, 'dose' => '1 comp', 'route' => 'oral', 'frequency_hours' => 8,
            'starts_at' => now()->subHours(40)->format('Y-m-d\TH:i'), 'ends_at' => now()->subHours(32)->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();
        foreach ($stay->doses()->get() as $dose) {
            $this->as()->post(route('hospitalizations.doses.give', $dose))->assertSessionHasNoErrors();
        }
        $this->as()->post(route('hospitalizations.charges.store', $stay), ['description' => 'Oxígeno', 'quantity' => 1, 'unit_price' => 30])->assertSessionHasNoErrors();
        $this->as()->post(route('hospitalizations.discharge', $stay), ['outcome' => 'discharged', 'discharged_at' => now()->format('Y-m-d\TH:i'), 'final_diagnosis' => 'Resuelto'])->assertSessionHasNoErrors();

        $stock = fn () => (float) ProductStock::allCompanies()->where('product_id', $product->id)->sum('quantity');
        $this->assertSame(8.0, $stock());

        $stay->refresh();
        $rows = $this->billable();
        $this->assertCount(1, $rows);
        $lines = collect($rows[0]['lines']);

        // Estadía + cargo manual + una sola línea para las dos tomas.
        $this->assertCount(3, $lines);
        $doses = $lines->firstWhere('type', 'product');
        $this->assertEquals(2, $doses['quantity']);
        $this->assertSame($product->id, $doses['product_id']);

        // Lo cobrado es exactamente lo que muestra la internación.
        $this->sell($lines->all())->assertSessionHasNoErrors();
        $sale = $this->lastSale();
        $this->assertSame($stay->totalCost(), (float) $sale->total);
        $this->assertSame($sale->id, $stay->fresh()->sale_id);
        $this->assertSame(8.0, $stock());

        // Anular tampoco repone: esas tomas se administraron.
        $this->as()->post(route('sales.cancel', $sale), ['cancel_reason' => 'Error', 'payment_method_id' => $this->cash->id])->assertSessionHasNoErrors();
        $this->assertSame(8.0, $stock());
        $this->assertNull($stay->fresh()->sale_id);
    }

    public function test_fallecimiento_tambien_queda_por_cobrar(): void
    {
        $this->closedStay(['status' => 'deceased']);

        $this->assertCount(1, $this->billable());
    }

    public function test_solo_se_cobra_al_propietario_de_la_mascota(): void
    {
        $stay = $this->closedStay();
        $lines = $this->billable()[0]['lines'];
        $otro = Client::factory()->create(['company_id' => $this->company->id]);

        $this->sell($lines, $otro->id, ['pet_id' => null])->assertSessionHasErrors('items');
        $this->assertNull($stay->fresh()->sale_id);
        $this->assertSame(0, Sale::allCompanies()->count());
    }

    public function test_no_se_cobra_lo_de_otra_veterinaria(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['laboratorio']]);
        $mascota = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $ajena->id])->id]);
        $estudio = LabRequest::factory()->create([
            'pet_id' => $mascota->id,
            'lab_study_type_id' => LabStudyType::factory()->create(['company_id' => $ajena->id])->id,
        ]);
        $estadia = Hospitalization::factory()->create(['pet_id' => $mascota->id, 'status' => 'discharged', 'discharged_at' => now()]);

        $this->sell([
            ['type' => 'lab', 'lab_request_id' => $estudio->id, 'description' => 'X', 'quantity' => 1, 'unit_price' => 1],
            ['type' => 'hospitalization', 'hospitalization_id' => $estadia->id, 'description' => 'X', 'quantity' => 1, 'unit_price' => 1],
        ])->assertSessionHasErrors(['items.0.lab_request_id', 'items.1.hospitalization_id']);

        $this->assertNull($estudio->fresh()->sale_id);
        $this->as()->getJson(route('pos.billable', $mascota))->assertNotFound();
    }

    public function test_el_catalogo_guarda_el_precio(): void
    {
        $this->as()->put(route('lab-studies.update', $this->study), ['name' => 'Perfil canino', 'category' => 'hematology', 'price' => 120, 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('120.00', $this->study->fresh()->price);

        $this->as()->put(route('lab-studies.update', $this->study), ['name' => 'Perfil canino', 'category' => 'hematology', 'price' => -1])->assertSessionHasErrors('price');
    }

    public function test_las_pantallas_muestran_el_estado_de_cobro(): void
    {
        $stay = $this->closedStay();
        $activa = Hospitalization::factory()->create(['pet_id' => $this->newPet()->id]);
        $request = $this->labRequest(['status' => 'requested']);

        $this->as()->get(route('hospitalizations.show', $stay))->assertOk()->assertSee('Cobrar en el punto de venta');
        $this->as()->get(route('hospitalizations.show', $activa))->assertOk()->assertSee('después del alta');
        $this->as()->get(route('lab.show', $request))->assertOk()->assertSee('Cobrar');
        $this->as()->get(route('pos.create', ['pet_id' => $this->pet->id]))->assertOk()->assertSee('Pendiente de cobro');

        $this->sell(collect($this->billable())->flatMap(fn ($row) => $row['lines'])->all())->assertSessionHasNoErrors();
        $sale = $this->lastSale();

        $this->as()->get(route('hospitalizations.show', $stay))->assertOk()->assertSee($sale->code)->assertDontSee('Cobrar en el punto de venta');
        $this->as()->get(route('lab.show', $request))->assertOk()->assertSee('Cobrado');
        $this->as()->get(route('sales.show', $sale))->assertOk()->assertSee('internación')->assertSee('estudio');
    }
}
