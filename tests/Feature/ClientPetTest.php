<?php

namespace Tests\Feature;

use App\Models\Breed;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use App\Support\ClinicalCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clientes, mascotas y su catálogo: aislamiento por veterinaria, reglas de
 * validación que cruzan tablas y el historial de peso.
 */
class ClientPetTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
        'pets.view', 'pets.create', 'pets.edit', 'pets.delete',
        'species.view', 'species.create', 'species.edit', 'species.delete',
    ];

    public function test_el_listado_solo_muestra_clientes_de_la_veterinaria_activa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        Client::factory()->create(['company_id' => $mia->id, 'first_name' => 'Propietaria Mía']);
        Client::factory()->create(['company_id' => $ajena->id, 'first_name' => 'Propietario Ajeno']);

        $user = $this->userInCompany($mia, ['clients.view']);

        $this->actingInCompany($user, $mia)->get(route('clients.index'))
            ->assertOk()->assertSee('Propietaria Mía')->assertDontSee('Propietario Ajeno');
    }

    public function test_un_cliente_o_mascota_de_otra_veterinaria_da_404(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $ajena->id])->id]);

        $user = $this->userInCompany($mia, self::ALL);

        $this->actingInCompany($user, $mia)->get(route('clients.show', $pet->client_id))->assertNotFound();
        $this->actingInCompany($user, $mia)->get(route('pets.show', $pet))->assertNotFound();
    }

    public function test_la_busqueda_de_propietarios_no_cruza_veterinarias(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        Client::factory()->create(['company_id' => $mia->id, 'first_name' => 'Rosa', 'last_name' => 'Mía']);
        Client::factory()->create(['company_id' => $ajena->id, 'first_name' => 'Rosa', 'last_name' => 'Ajena']);

        $user = $this->userInCompany($mia, ['pets.create']);

        $this->actingInCompany($user, $mia)->getJson(route('clients.search', ['q' => 'rosa']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Rosa Mía');
    }

    public function test_el_documento_es_unico_por_veterinaria_y_no_global(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        Client::factory()->create(['company_id' => $ajena->id, 'document_number' => '1234567']);

        $user = $this->userInCompany($mia, self::ALL);
        $data = ['first_name' => 'Ana', 'document_type' => 'CI', 'document_number' => '1234567'];

        // En otra veterinaria el mismo documento es válido…
        $this->actingInCompany($user, $mia)->post(route('clients.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame($mia->id, Client::allCompanies()->where('first_name', 'Ana')->value('company_id'));

        // …pero no se puede repetir dentro de la misma.
        $this->actingInCompany($user, $mia)->post(route('clients.store'), $data)->assertSessionHasErrors('document_number');
    }

    public function test_no_acepta_especie_o_propietario_de_otra_veterinaria(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $clienteAjeno = Client::factory()->create(['company_id' => $ajena->id]);
        $especieAjena = Species::factory()->create(['company_id' => $ajena->id]);

        $user = $this->userInCompany($mia, self::ALL);

        $this->actingInCompany($user, $mia)->post(route('pets.store'), [
            'client_id' => $clienteAjeno->id,
            'species_id' => $especieAjena->id,
            'name' => 'Intrusa',
            'sex' => 'female',
            'status' => 'active',
        ])->assertSessionHasErrors(['client_id', 'species_id']);

        $this->assertSame(0, Pet::allCompanies()->count());
    }

    public function test_la_raza_debe_ser_de_la_especie_elegida(): void
    {
        $company = $this->companyWithPlan();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $perro = Species::factory()->create(['company_id' => $company->id, 'name' => 'Canino']);
        $gato = Species::factory()->create(['company_id' => $company->id, 'name' => 'Felino']);
        $siames = Breed::factory()->create(['species_id' => $gato->id, 'name' => 'Siamés']);

        $user = $this->userInCompany($company, self::ALL);

        $this->actingInCompany($user, $company)->post(route('pets.store'), [
            'client_id' => $client->id,
            'species_id' => $perro->id,
            'breed_id' => $siames->id,
            'name' => 'Firulais',
            'sex' => 'male',
            'status' => 'active',
        ])->assertSessionHasErrors('breed_id');
    }

    public function test_el_peso_lleva_historial_y_el_vigente_es_el_mas_reciente(): void
    {
        $company = $this->companyWithPlan();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $species = Species::factory()->create(['company_id' => $company->id]);
        $user = $this->userInCompany($company, self::ALL);

        $this->actingInCompany($user, $company)->post(route('pets.store'), [
            'client_id' => $client->id,
            'species_id' => $species->id,
            'name' => 'Luna',
            'sex' => 'female',
            'status' => 'active',
            'weight' => 10,
        ])->assertSessionHasNoErrors();

        $pet = Pet::allCompanies()->where('name', 'Luna')->firstOrFail();
        $this->assertSame($company->id, $pet->company_id);
        $this->assertCount(1, $pet->weights()->allCompanies()->get());

        // Un registro antiguo no cambia el peso vigente.
        $this->actingInCompany($user, $company)->post(route('pets.weights.store', $pet), [
            'weight' => 8, 'recorded_at' => now()->subYear()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertEquals(10, $pet->fresh()->weight);

        // Uno nuevo sí.
        $this->actingInCompany($user, $company)->post(route('pets.weights.store', $pet), [
            'weight' => 12.5, 'recorded_at' => now()->toDateString(),
        ]);
        $this->assertEquals(12.5, $pet->fresh()->weight);
        $this->assertSame(3, $pet->weights()->allCompanies()->count());
    }

    public function test_no_se_elimina_un_cliente_con_mascotas(): void
    {
        $company = $this->companyWithPlan();
        $pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $company->id])->id]);
        $user = $this->userInCompany($company, self::ALL);

        $this->actingInCompany($user, $company)->delete(route('clients.destroy', $pet->client_id))
            ->assertSessionHasErrors('error');

        $this->assertNotNull(Client::allCompanies()->find($pet->client_id));
    }

    public function test_sin_permiso_no_registra_mascotas(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['pets.view']);

        $this->actingInCompany($user, $company)->get(route('pets.index'))->assertOk();
        $this->actingInCompany($user, $company)->get(route('pets.create'))->assertForbidden();
    }

    public function test_en_modo_global_el_superadmin_debe_entrar_como_una_veterinaria(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get(route('clients.index'))->assertRedirect(route('companies.index'));
    }

    public function test_el_catalogo_base_es_idempotente_y_respeta_lo_editado(): void
    {
        $company = $this->companyWithPlan();

        ClinicalCatalog::seedFor($company);
        $count = Species::forCompany($company)->count();

        Species::forCompany($company)->where('name', 'Otro')->update(['active' => false]);
        ClinicalCatalog::seedFor($company);

        $this->assertSame($count, Species::forCompany($company)->count());
        $this->assertFalse((bool) Species::forCompany($company)->where('name', 'Otro')->value('active'));
        $this->assertSame(count(ClinicalCatalog::SPECIES), $count);
    }

    public function test_la_ficha_muestra_las_alertas_clinicas(): void
    {
        $company = $this->companyWithPlan();
        $pet = Pet::factory()->create([
            'client_id' => Client::factory()->create(['company_id' => $company->id])->id,
            'allergies' => 'Penicilina',
        ]);
        $user = $this->userInCompany($company, ['pets.view']);

        $this->actingInCompany($user, $company)->get(route('pets.show', $pet))
            ->assertOk()->assertSee('Alertas clínicas')->assertSee('Penicilina');
    }

    public function test_edad_legible(): void
    {
        $this->assertSame('2 años 3 meses', (new Pet(['birth_date' => now()->subYears(2)->subMonths(3)]))->age_label);
        $this->assertSame('5 meses', (new Pet(['birth_date' => now()->subMonths(5)]))->age_label);
        $this->assertSame('1 año', (new Pet(['birth_date' => now()->subYear()]))->age_label);
        $this->assertNull((new Pet())->age_label);
    }
}
