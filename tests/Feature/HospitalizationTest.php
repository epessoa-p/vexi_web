<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Hospitalization;
use App\Models\HospitalizationDose;
use App\Models\Kennel;
use App\Models\Pet;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Stock;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hospitalización: un paciente y un espacio por internación activa, tomas
 * programadas que descuentan stock, costos, y un alta que cierra todo.
 */
class HospitalizationTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'hospitalizations.view', 'hospitalizations.create', 'hospitalizations.edit',
        'kennels.view', 'kennels.create', 'kennels.edit', 'kennels.delete', 'pets.view', 'consultations.view',
    ];

    private Company $company;

    private User $vet;

    private Pet $pet;

    private Kennel $kennel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['hospitalizacion', 'inventario', 'consultas']]);
        $this->vet = $this->userInCompany($this->company, self::ALL, 'veterinarian');
        $this->pet = $this->newPet();
        $this->kennel = Kennel::factory()->create(['company_id' => $this->company->id, 'name' => 'Canil 1']);
    }

    private function newPet(): Pet
    {
        return Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);
    }

    private function as()
    {
        return $this->actingInCompany($this->vet, $this->company);
    }

    private function stay(array $attributes = []): Hospitalization
    {
        return Hospitalization::factory()->create(['pet_id' => $this->pet->id] + $attributes);
    }

    /** @return array<string, mixed> */
    private function admission(Pet $pet, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $pet->client_id,
            'pet_id' => $pet->id,
            'admitted_at' => now()->subMinutes(5)->format('Y-m-d\TH:i'),
            'condition' => 'observation',
            'reason' => 'Vómitos',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function treatment(array $overrides = []): array
    {
        return array_merge([
            'medication' => 'Suero',
            'dose' => '100 ml',
            'route' => 'iv',
            'frequency_hours' => 8,
            'starts_at' => '2026-11-10T08:00',
            'ends_at' => '2026-11-11T08:00',
        ], $overrides);
    }

    private function pendingDose(): HospitalizationDose
    {
        return HospitalizationDose::allCompanies()->where('status', 'pending')->orderBy('scheduled_at')->firstOrFail();
    }

    public function test_sin_el_modulo_no_entra(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['consultas']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('hospitalizations.index'))->assertForbidden();
        $this->actingInCompany($user, $sin)->get(route('kennels.index'))->assertForbidden();
    }

    public function test_admision_ligada_a_la_consulta(): void
    {
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id]);

        $this->as()->post(route('hospitalizations.store'), $this->admission($this->pet, [
            'consultation_id' => $consultation->id, 'kennel_id' => $this->kennel->id, 'daily_rate' => 150,
        ]))->assertSessionHasNoErrors();

        $stay = Hospitalization::allCompanies()->firstOrFail();
        $this->assertSame($this->company->id, $stay->company_id);
        $this->assertSame($consultation->id, $stay->consultation_id);
        $this->assertSame($this->vet->id, $stay->veterinarian_id);
        $this->assertSame('active', $stay->status);
    }

    public function test_un_paciente_y_un_espacio_por_internacion_activa(): void
    {
        $this->as()->post(route('hospitalizations.store'), $this->admission($this->pet, ['kennel_id' => $this->kennel->id]))->assertSessionHasNoErrors();

        // El mismo paciente no entra dos veces.
        $this->as()->post(route('hospitalizations.store'), $this->admission($this->pet))->assertSessionHasErrors('pet_id');

        // Otro paciente no ocupa el mismo espacio.
        $otro = $this->newPet();
        $this->as()->post(route('hospitalizations.store'), $this->admission($otro, ['kennel_id' => $this->kennel->id]))->assertSessionHasErrors('kennel_id');
        $this->assertSame(1, Hospitalization::allCompanies()->count());

        // Con el alta el espacio queda libre.
        $stay = Hospitalization::allCompanies()->firstOrFail();
        $this->as()->post(route('hospitalizations.discharge', $stay), ['outcome' => 'discharged', 'discharged_at' => now()->format('Y-m-d\TH:i'), 'final_diagnosis' => 'Resuelto'])
            ->assertSessionHasNoErrors();
        $this->as()->post(route('hospitalizations.store'), $this->admission($otro, ['kennel_id' => $this->kennel->id]))->assertSessionHasNoErrors();
    }

    public function test_rechaza_datos_de_otra_veterinaria_y_mascota_fallecida(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['hospitalizacion']]);
        $espacioAjeno = Kennel::factory()->create(['company_id' => $ajena->id]);
        $vetAjeno = $this->userInCompany($ajena, [], 'veterinarian');
        $estadiaAjena = Hospitalization::factory()->create(['pet_id' => Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $ajena->id])->id])->id]);
        $fallecida = $this->newPet();
        $fallecida->update(['status' => 'deceased']);

        $this->as()->post(route('hospitalizations.store'), $this->admission($this->pet, ['kennel_id' => $espacioAjeno->id]))->assertSessionHasErrors('kennel_id');
        $this->as()->post(route('hospitalizations.store'), $this->admission($this->pet, ['veterinarian_id' => $vetAjeno->id]))->assertSessionHasErrors('veterinarian_id');
        $this->as()->post(route('hospitalizations.store'), $this->admission($fallecida))->assertSessionHasErrors('pet_id');
        $this->as()->get(route('hospitalizations.show', $estadiaAjena))->assertNotFound();
    }

    public function test_el_tratamiento_genera_su_horario_y_limita_las_tomas(): void
    {
        $stay = $this->stay();

        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment())->assertSessionHasNoErrors();

        // De 08:00 a 08:00 del día siguiente, cada 8 h, fin inclusive.
        $slots = HospitalizationDose::allCompanies()->orderBy('scheduled_at')->get()->map(fn ($d) => $d->scheduled_at->format('d H:i'))->all();
        $this->assertSame(['10 08:00', '10 16:00', '11 00:00', '11 08:00'], $slots);

        // Una frecuencia absurda para el período se rechaza sin crear filas.
        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment(['frequency_hours' => 1, 'ends_at' => '2026-12-30T08:00']))
            ->assertSessionHasErrors('ends_at');
        $this->assertSame(4, HospitalizationDose::allCompanies()->count());
    }

    public function test_aplicar_una_toma_descuenta_stock_y_deja_el_costo(): void
    {
        $branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->kennel->update(['branch_id' => $branch->id]);
        $product = Product::factory()->create(['company_id' => $this->company->id, 'name' => 'Amoxicilina', 'track_batches' => true, 'sale_price' => 10]);
        app(Tenancy::class)->runFor($this->company->id, fn () => Stock::receive($product, $branch, 2, 'initial', ['lot' => 'L1', 'expires_at' => now()->addYear()]));
        $stay = $this->stay(['kennel_id' => $this->kennel->id]);

        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment(['medication' => '', 'product_id' => $product->id, 'stock_quantity' => 1.5]))->assertSessionHasNoErrors();
        $this->assertSame('Amoxicilina', $stay->treatments()->first()->medication);

        $dose = $this->pendingDose();
        $this->as()->post(route('hospitalizations.doses.give', $dose))->assertSessionHasNoErrors();

        $this->assertSame('given', $dose->fresh()->status);
        $this->assertSame($this->vet->id, $dose->fresh()->done_by);
        $this->assertSame(0.5, (float) ProductBatch::allCompanies()->value('quantity'));
        $this->assertSame('hospitalization', StockMovement::allCompanies()->latest('id')->value('reason'));
        $this->assertSame('15.00', $stay->charges()->allCompanies()->value('total'));

        // Una toma no se aplica dos veces.
        $this->as()->post(route('hospitalizations.doses.give', $dose))->assertSessionHasErrors('error');
        $this->assertSame(0.5, (float) ProductStock::allCompanies()->value('quantity'));

        // Ya aplicada: si falta stock se registra igual y queda en negativo.
        $this->as()->post(route('hospitalizations.doses.give', $this->pendingDose()))->assertSessionHasNoErrors();
        $this->assertSame(-1.0, (float) ProductStock::allCompanies()->value('quantity'));

        // Su cargo no se quita suelto.
        $charge = $stay->charges()->allCompanies()->first();
        $this->as()->delete(route('hospitalizations.charges.destroy', [$stay, $charge]))->assertSessionHasErrors('error');
    }

    public function test_omitir_exige_motivo_y_suspender_cancela_lo_pendiente(): void
    {
        $stay = $this->stay();
        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment());
        $dose = $this->pendingDose();

        $this->as()->post(route('hospitalizations.doses.skip', $dose))->assertSessionHasErrors('notes');
        $this->as()->post(route('hospitalizations.doses.skip', $dose), ['notes' => 'Vomitó'])->assertSessionHasNoErrors();
        $this->assertSame('skipped', $dose->fresh()->status);

        $this->as()->post(route('hospitalizations.treatments.stop', [$stay, $stay->treatments()->first()]))->assertSessionHasNoErrors();
        $this->assertSame(0, HospitalizationDose::allCompanies()->where('status', 'pending')->count());
        $this->assertSame('skipped', $dose->fresh()->status);
    }

    public function test_el_registro_actualiza_estado_y_peso(): void
    {
        $stay = $this->stay();

        $this->as()->post(route('hospitalizations.records.store', $stay), ['type' => 'care', 'recorded_at' => now()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('notes');

        $this->as()->post(route('hospitalizations.records.store', $stay), [
            'type' => 'vitals', 'recorded_at' => now()->format('Y-m-d\TH:i'), 'temperature' => 39.1, 'weight' => 7.5, 'condition' => 'critical',
        ])->assertSessionHasNoErrors();

        $this->assertSame('critical', $stay->fresh()->condition);
        $this->assertEquals(7.5, $this->pet->fresh()->weight);
        $this->assertSame($this->vet->id, $stay->records()->allCompanies()->value('recorded_by'));
    }

    public function test_costos_por_dia_o_fraccion_mas_cargos(): void
    {
        $stay = $this->stay(['admitted_at' => now()->subHours(25), 'daily_rate' => 100]);
        $this->assertSame(2, $stay->stayDays());

        $this->as()->post(route('hospitalizations.charges.store', $stay), ['description' => 'Oxígeno', 'quantity' => 2, 'unit_price' => 30])->assertSessionHasNoErrors();

        $this->assertSame(260.0, $stay->fresh()->totalCost());
        $this->assertSame(1, Hospitalization::factory()->make(['admitted_at' => now()->subMinutes(10)])->stayDays());
    }

    public function test_el_alta_cierra_la_internacion(): void
    {
        $stay = $this->stay();
        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment());

        // No antes del ingreso.
        $this->as()->post(route('hospitalizations.discharge', $stay), ['outcome' => 'discharged', 'discharged_at' => now()->subDays(2)->format('Y-m-d\TH:i'), 'final_diagnosis' => 'X'])
            ->assertSessionHasErrors('discharged_at');

        $this->as()->post(route('hospitalizations.discharge', $stay), [
            'outcome' => 'discharged', 'discharged_at' => now()->format('Y-m-d\TH:i'), 'final_diagnosis' => 'Gastroenteritis resuelta', 'home_treatment' => 'Dieta blanda',
        ])->assertSessionHasNoErrors();

        $closed = $stay->fresh();
        $this->assertSame('discharged', $closed->status);
        $this->assertSame($this->vet->id, $closed->discharged_by);
        $this->assertSame(0, HospitalizationDose::allCompanies()->where('status', 'pending')->count());
        $this->assertSame('active', $this->pet->fresh()->status);

        // Cerrada no admite cambios.
        $this->as()->post(route('hospitalizations.records.store', $stay), ['type' => 'care', 'recorded_at' => now()->format('Y-m-d\TH:i'), 'notes' => 'X'])->assertSessionHasErrors('error');
        $this->as()->post(route('hospitalizations.charges.store', $stay), ['description' => 'X', 'quantity' => 1, 'unit_price' => 1])->assertSessionHasErrors('error');
        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment())->assertSessionHasErrors('error');
        $this->as()->post(route('hospitalizations.discharge', $stay), ['outcome' => 'deceased', 'discharged_at' => now()->format('Y-m-d\TH:i'), 'final_diagnosis' => 'X'])->assertSessionHasErrors('error');

        $this->as()->get(route('hospitalizations.report', $stay))->assertOk()->assertSee('Gastroenteritis resuelta')->assertSee('Dieta blanda');
    }

    public function test_el_fallecimiento_actualiza_la_ficha(): void
    {
        $stay = $this->stay();

        $this->as()->post(route('hospitalizations.discharge', $stay), ['outcome' => 'deceased', 'discharged_at' => now()->format('Y-m-d\TH:i'), 'final_diagnosis' => 'Falla renal'])
            ->assertSessionHasNoErrors();

        $this->assertSame('deceased', $stay->fresh()->status);
        $this->assertSame('deceased', $this->pet->fresh()->status);
    }

    public function test_anular_solo_si_no_se_atendio(): void
    {
        $atendida = $this->stay();
        $this->as()->post(route('hospitalizations.records.store', $atendida), ['type' => 'care', 'recorded_at' => now()->format('Y-m-d\TH:i'), 'notes' => 'Baño']);
        $this->as()->post(route('hospitalizations.cancel', $atendida), ['cancel_reason' => 'Error'])->assertSessionHasErrors('cancel_reason');

        $error = Hospitalization::factory()->create(['pet_id' => $this->newPet()->id]);
        $this->as()->post(route('hospitalizations.treatments.store', $error), $this->treatment());
        $this->as()->post(route('hospitalizations.cancel', $error), ['cancel_reason' => 'Paciente equivocado'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $error->fresh()->status);
        $this->as()->get(route('hospitalizations.report', $error))->assertNotFound();
    }

    public function test_el_auxiliar_cuida_pero_no_admite_ni_da_de_alta(): void
    {
        $stay = $this->stay();
        $auxiliar = $this->userInCompany($this->company, ['hospitalizations.view', 'hospitalizations.edit'], 'auxiliar');
        $as = fn () => $this->actingInCompany($auxiliar, $this->company);

        $as()->post(route('hospitalizations.records.store', $stay), ['type' => 'care', 'recorded_at' => now()->format('Y-m-d\TH:i'), 'notes' => 'Limpieza'])->assertSessionHasNoErrors();
        $as()->get(route('hospitalizations.create'))->assertForbidden();
        $as()->post(route('hospitalizations.discharge', $stay), ['outcome' => 'discharged', 'discharged_at' => now()->format('Y-m-d\TH:i'), 'final_diagnosis' => 'X'])->assertForbidden();
        $as()->get(route('hospitalizations.show', $stay))->assertOk()->assertDontSee('Dar de alta');
    }

    public function test_espacios_con_historial_no_se_eliminan(): void
    {
        $this->as()->post(route('kennels.store'), ['name' => 'Canil 1'])->assertSessionHasErrors('name');
        $this->stay(['kennel_id' => $this->kennel->id]);

        $this->as()->delete(route('kennels.destroy', $this->kennel))->assertSessionHasErrors('error');
        $libre = Kennel::factory()->create(['company_id' => $this->company->id]);
        $this->as()->delete(route('kennels.destroy', $libre))->assertSessionHasNoErrors();
    }

    public function test_las_pantallas_cargan(): void
    {
        $stay = $this->stay(['kennel_id' => $this->kennel->id]);
        $this->as()->post(route('hospitalizations.treatments.store', $stay), $this->treatment(['starts_at' => now()->subHour()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->format('Y-m-d\TH:i')]));
        $consultation = Consultation::factory()->create(['pet_id' => $this->newPet()->id]);

        $as = $this->as();
        $as->get(route('hospitalizations.index'))->assertOk()->assertSee($this->pet->name)->assertSee('Atrasada');
        $as->get(route('hospitalizations.index', ['status' => 'discharged']))->assertOk();
        $as->get(route('hospitalizations.create', ['consultation_id' => $consultation->id]))->assertOk();
        $as->get(route('hospitalizations.show', $stay))->assertOk()->assertSee('Horario de administración');
        $as->get(route('kennels.index'))->assertOk()->assertSee('Canil 1');
        $as->get(route('pets.show', ['pet' => $this->pet, 'tab' => 'record']))->assertOk()->assertSee('Internado');
        $as->get(route('dashboard'))->assertOk()->assertSee('Tomas atrasadas');
    }
}
