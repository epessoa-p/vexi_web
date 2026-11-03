<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Deworming;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Support\ClinicalCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vacunas y desparasitaciones: cálculo de la próxima dosis por protocolo,
 * validaciones (lote vencido, especie), qué cuenta como pendiente y el
 * calendario sanitario.
 */
class HealthTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'vaccinations.view', 'vaccinations.create', 'vaccinations.edit', 'vaccinations.delete',
        'vaccines.view', 'vaccines.create', 'vaccines.edit', 'pets.view',
    ];

    private Company $company;

    private User $vet;

    private Species $dog;

    private Pet $pet;

    private Vaccine $vaccine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['vacunas']]);
        $this->vet = $this->userInCompany($this->company, self::ALL, 'veterinarian');
        $this->dog = Species::factory()->create(['company_id' => $this->company->id, 'name' => 'Canino']);
        $this->pet = Pet::factory()->create([
            'client_id' => Client::factory()->create(['company_id' => $this->company->id])->id,
            'species_id' => $this->dog->id,
        ]);
        // Serie de 3 dosis cada 21 días, refuerzo anual; solo para perros.
        $this->vaccine = Vaccine::factory()->create(['company_id' => $this->company->id, 'name' => 'Polivalente']);
        $this->vaccine->species()->attach($this->dog->id);
    }

    private function as()
    {
        return $this->actingInCompany($this->vet, $this->company);
    }

    private function apply(array $overrides = [])
    {
        return $this->as()->post(route('vaccinations.store'), array_merge([
            'pet_id' => $this->pet->id,
            'vaccine_id' => $this->vaccine->id,
            'applied_at' => '2026-01-10',
            'dose_number' => 1,
        ], $overrides));
    }

    public function test_sin_el_modulo_vacunas_no_entra(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('health.index'))->assertForbidden();
        $this->actingInCompany($user, $sin)->get(route('vaccines.index'))->assertForbidden();
    }

    public function test_la_proxima_dosis_sale_del_protocolo(): void
    {
        // Dentro de la serie: +21 días.
        $this->apply()->assertSessionHasNoErrors();
        $this->assertSame('2026-01-31', Vaccination::allCompanies()->latest('id')->first()->next_due_at->toDateString());

        // Última dosis de la serie: pasa al refuerzo anual.
        $this->apply(['dose_number' => 3, 'applied_at' => '2026-02-21']);
        $this->assertSame('2027-02-21', Vaccination::allCompanies()->latest('id')->first()->next_due_at->toDateString());

        // Se puede pedir que no se programe otra.
        $this->apply(['dose_number' => 4, 'applied_at' => '2026-03-01', 'no_next_due' => 1]);
        $this->assertNull(Vaccination::allCompanies()->latest('id')->first()->next_due_at);
    }

    public function test_rechaza_lote_vencido_y_vacuna_de_otra_especie(): void
    {
        $this->apply(['lot' => 'A1', 'lot_expires_at' => '2025-12-31'])->assertSessionHasErrors('lot_expires_at');

        $felina = Vaccine::factory()->create(['company_id' => $this->company->id]);
        $felina->species()->attach(Species::factory()->create(['company_id' => $this->company->id, 'name' => 'Felino'])->id);
        $this->apply(['vaccine_id' => $felina->id])->assertSessionHasErrors('vaccine_id');

        $this->assertSame(0, Vaccination::allCompanies()->count());
    }

    public function test_no_registra_en_mascotas_ni_con_vacunas_ajenas(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['vacunas']]);
        $petAjena = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $ajena->id])->id]);
        $vacunaAjena = Vaccine::factory()->create(['company_id' => $ajena->id]);

        $this->apply(['pet_id' => $petAjena->id])->assertNotFound();
        $this->apply(['vaccine_id' => $vacunaAjena->id])->assertSessionHasErrors('vaccine_id');
    }

    public function test_una_aplicacion_posterior_cubre_la_pendiente(): void
    {
        $old = Vaccination::factory()->create(['pet_id' => $this->pet->id, 'vaccine_id' => $this->vaccine->id,
            'applied_at' => now()->subYear()->toDateString(), 'next_due_at' => now()->subDays(5)->toDateString()]);

        $this->as()->get(route('health.index', ['tab' => 'overdue']))->assertOk()->assertSee($this->pet->name);

        // Se aplica el refuerzo: la atrasada deja de figurar.
        $this->apply(['applied_at' => now()->toDateString(), 'dose_number' => 4]);

        $this->assertSame(0, Vaccination::allCompanies()->pendingDue()->where('next_due_at', '<', now()->startOfDay())->count());
        $this->as()->get(route('health.index', ['tab' => 'overdue']))->assertOk()->assertDontSee($this->pet->name);
        $this->assertTrue(Vaccination::allCompanies()->pendingDue()->get()->doesntContain('id', $old->id));
    }

    public function test_desparasitacion_ambas_cubre_la_interna_pero_no_al_reves(): void
    {
        $this->as()->post(route('dewormings.store'), [
            'pet_id' => $this->pet->id, 'type' => 'internal', 'product' => 'Drontal', 'applied_at' => now()->subDays(100)->toDateString(),
        ])->assertSessionHasNoErrors();
        $internal = Deworming::allCompanies()->firstOrFail();
        $this->assertSame(now()->subDays(10)->toDateString(), $internal->next_due_at->toDateString());   // 90 días

        $this->as()->post(route('dewormings.store'), [
            'pet_id' => $this->pet->id, 'type' => 'external', 'product' => 'Pipeta', 'applied_at' => now()->toDateString(),
        ]);
        // Una externa no cubre la interna atrasada.
        $this->assertTrue(Deworming::allCompanies()->pendingDue()->get()->contains('id', $internal->id));

        $this->as()->post(route('dewormings.store'), [
            'pet_id' => $this->pet->id, 'type' => 'both', 'product' => 'Simparica Trio', 'applied_at' => now()->toDateString(),
        ]);
        $this->assertFalse(Deworming::allCompanies()->pendingDue()->get()->contains('id', $internal->id));
    }

    public function test_ficha_carnet_y_alertas(): void
    {
        Vaccination::factory()->create(['pet_id' => $this->pet->id, 'vaccine_id' => $this->vaccine->id,
            'lot' => 'LOTE-77', 'next_due_at' => now()->subDays(3)->toDateString()]);

        $this->as()->get(route('pets.show', ['pet' => $this->pet, 'tab' => 'health']))
            ->assertOk()->assertSee('Atrasado')->assertSee('Polivalente')->assertSee('LOTE-77');
        $this->as()->get(route('health.card', $this->pet))->assertOk()->assertSee('Carnet sanitario')->assertSee('LOTE-77');
        $this->as()->get(route('dashboard'))->assertOk()->assertSee('Alertas sanitarias');
    }

    public function test_el_catalogo_base_trae_vacunas_con_sus_especies(): void
    {
        ClinicalCatalog::seedFor($this->company);

        // Con la veterinaria activa, como en una petición real (sin ella el scope no devuelve nada).
        $rabia = app(Tenancy::class)->runFor($this->company->id, fn () => Vaccine::where('name', 'Antirrábica')->with('species')->firstOrFail());
        $this->assertEqualsCanonicalizing(['Canino', 'Felino'], $rabia->species->pluck('name')->all());
        $this->assertSame(365, $rabia->booster_interval_days);
    }

    public function test_las_pantallas_cargan(): void
    {
        $as = $this->as();
        $as->get(route('health.index', ['tab' => 'upcoming']))->assertOk();
        $as->get(route('health.index', ['tab' => 'history']))->assertOk();
        $as->get(route('vaccinations.create'))->assertOk()->assertSee('Elige el paciente');
        $as->get(route('vaccinations.create', ['pet_id' => $this->pet->id]))->assertOk()->assertSee('Polivalente');
        $as->get(route('dewormings.create', ['pet_id' => $this->pet->id]))->assertOk();
        $as->get(route('vaccines.index'))->assertOk();
        $as->get(route('vaccines.create'))->assertOk();
        $as->get(route('vaccines.edit', $this->vaccine))->assertOk();
    }
}
