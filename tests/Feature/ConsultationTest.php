<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consultas: inicio desde la cita, borrador, cierre (y lo que el cierre
 * dispara), bloqueo de la consulta cerrada, receta y aislamiento.
 */
class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'consultations.view', 'consultations.create', 'consultations.edit', 'consultations.delete',
        'appointments.view', 'appointments.edit', 'pets.view',
    ];

    private Company $company;

    private User $vet;

    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['agenda', 'consultas']]);
        $this->vet = $this->userInCompany($this->company, self::ALL, 'veterinarian');
        $this->pet = Pet::factory()->create([
            'client_id' => Client::factory()->create(['company_id' => $this->company->id])->id,
            'weight' => 10,
        ]);
    }

    private function as()
    {
        return $this->actingInCompany($this->vet, $this->company);
    }

    /** @return array<string, mixed> */
    private function clinicalData(array $overrides = []): array
    {
        return array_merge([
            'reason' => 'Vómitos',
            'temperature' => 39.2,
            'weight' => 11.5,
            'diagnoses' => [
                ['type' => 'primary', 'description' => 'Gastroenteritis', 'code' => 'GI-01'],
                ['type' => 'secondary', 'description' => ''],   // fila vacía: se ignora
            ],
            'prescription' => [
                ['medication' => 'Metronidazol', 'dose' => '1 comp.', 'route' => 'oral', 'frequency' => 'cada 12 h', 'duration' => '5 días'],
            ],
            'procedures' => [
                ['name' => 'Fluidoterapia SC', 'performed_by' => $this->vet->id, 'cost' => 50],
            ],
            'instructions' => 'Dieta blanda',
        ], $overrides);
    }

    public function test_sin_el_modulo_consultas_en_el_plan_no_entra(): void
    {
        $sinConsultas = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinConsultas, self::ALL, 'otro');

        $this->actingInCompany($user, $sinConsultas)->get(route('consultations.index'))->assertForbidden();
    }

    public function test_iniciar_desde_la_cita_la_pone_en_atencion_una_sola_vez(): void
    {
        $appointment = Appointment::factory()->create(['pet_id' => $this->pet->id, 'status' => 'waiting', 'reason' => 'Cojera']);

        $this->as()->post(route('consultations.start-from-appointment', $appointment))->assertRedirect();

        $consultation = Consultation::allCompanies()->firstOrFail();
        $this->assertSame($appointment->id, $consultation->appointment_id);
        $this->assertSame('Cojera', $consultation->reason);
        $this->assertSame($this->vet->id, $consultation->veterinarian_id);
        $this->assertSame('in_progress', $appointment->fresh()->status);

        // Un segundo clic lleva a la misma consulta, no crea otra.
        $this->as()->post(route('consultations.start-from-appointment', $appointment))
            ->assertRedirect(route('consultations.edit', $consultation));
        $this->assertSame(1, Consultation::allCompanies()->count());
    }

    public function test_el_borrador_guarda_filas_e_ignora_las_vacias(): void
    {
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id]);

        $this->as()->put(route('consultations.update', $consultation), $this->clinicalData())
            ->assertRedirect(route('consultations.edit', $consultation));

        $consultation->refresh();
        $this->assertSame('in_progress', $consultation->status);
        $this->assertCount(1, $consultation->diagnoses()->allCompanies()->get());
        $this->assertSame('Metronidazol', $consultation->prescriptionItems()->allCompanies()->value('medication'));

        // Guardar de nuevo reemplaza las filas, no las duplica.
        $this->as()->put(route('consultations.update', $consultation), $this->clinicalData(['prescription' => []]));
        $this->assertSame(0, $consultation->prescriptionItems()->allCompanies()->count());
        $this->assertSame(1, $consultation->diagnoses()->allCompanies()->count());
    }

    public function test_finalizar_exige_diagnostico_principal(): void
    {
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id]);

        $this->as()->put(route('consultations.update', $consultation), $this->clinicalData([
            'finish' => 1,
            'diagnoses' => [['type' => 'differential', 'description' => 'Pancreatitis']],
        ]))->assertSessionHasErrors('diagnoses');

        $this->assertSame('in_progress', $consultation->fresh()->status);
    }

    public function test_finalizar_cierra_la_cita_y_registra_el_peso(): void
    {
        $appointment = Appointment::factory()->create(['pet_id' => $this->pet->id, 'status' => 'waiting']);
        $this->as()->post(route('consultations.start-from-appointment', $appointment));
        $consultation = Consultation::allCompanies()->firstOrFail();

        $this->as()->put(route('consultations.update', $consultation), $this->clinicalData(['finish' => 1]))
            ->assertRedirect(route('consultations.show', $consultation));

        $this->assertSame('finished', $consultation->fresh()->status);
        $this->assertNotNull($consultation->fresh()->finished_at);
        $this->assertSame('attended', $appointment->fresh()->status);
        $this->assertEquals(11.5, $this->pet->fresh()->weight);
    }

    public function test_una_consulta_finalizada_no_se_edita_ni_se_elimina(): void
    {
        $consultation = Consultation::factory()->finished()->create(['pet_id' => $this->pet->id, 'reason' => 'Original']);

        $this->as()->get(route('consultations.edit', $consultation))->assertRedirect(route('consultations.show', $consultation));
        $this->as()->put(route('consultations.update', $consultation), ['reason' => 'Cambiado'])->assertSessionHasErrors('error');
        $this->as()->delete(route('consultations.destroy', $consultation))->assertSessionHasErrors('error');

        $this->assertSame('Original', $consultation->fresh()->reason);
        $this->assertNull($consultation->fresh()->deleted_at);
    }

    public function test_seguimiento_de_una_consulta_cerrada(): void
    {
        $consultation = Consultation::factory()->finished()->create(['pet_id' => $this->pet->id]);

        $this->as()->post(route('consultations.followups.store', $consultation), [
            'treatment_status' => 'improving',
            'notes' => 'Ya no vomita.',
        ])->assertSessionHasNoErrors();

        $this->as()->get(route('consultations.show', $consultation))->assertOk()->assertSee('Ya no vomita.')->assertSee('Mejorando');
    }

    public function test_la_receta_lleva_medicamentos_y_datos_del_profesional(): void
    {
        $this->vet->update(['professional_license' => 'MAT-777']);
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id, 'veterinarian_id' => $this->vet->id]);
        $this->as()->put(route('consultations.update', $consultation), $this->clinicalData(['finish' => 1]));

        $this->as()->get(route('consultations.prescription', $consultation))
            ->assertOk()
            ->assertSee('Metronidazol')
            ->assertSee('cada 12 h')
            ->assertSee('MAT-777')
            ->assertSee($consultation->prescription_number);
    }

    public function test_no_cruza_veterinarias(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['consultas']]);
        $consultaAjena = Consultation::factory()->create([
            'pet_id' => Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $ajena->id])->id])->id,
        ]);
        $vetAjeno = $this->userInCompany($ajena, [], 'veterinarian');
        $mia = Consultation::factory()->create(['pet_id' => $this->pet->id]);

        $this->as()->get(route('consultations.show', $consultaAjena))->assertNotFound();
        $this->as()->put(route('consultations.update', $mia), $this->clinicalData([
            'procedures' => [['name' => 'Sutura', 'performed_by' => $vetAjeno->id]],
        ]))->assertSessionHasErrors('procedures.0.performed_by');
    }

    public function test_la_ficha_muestra_el_expediente_y_el_perfil_profesional_se_guarda(): void
    {
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id]);
        $this->as()->put(route('consultations.update', $consultation), $this->clinicalData(['finish' => 1]));

        $this->as()->get(route('pets.show', ['pet' => $this->pet, 'tab' => 'record']))
            ->assertOk()->assertSee('Gastroenteritis')->assertSee('Metronidazol');

        $this->as()->put(route('profile.professional.update'), ['professional_license' => 'COL-1', 'specialty' => 'Cirugía'])
            ->assertSessionHasNoErrors();
        $this->assertSame('COL-1', $this->vet->fresh()->professional_license);
    }

    public function test_las_pantallas_cargan(): void
    {
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id]);

        $this->as()->get(route('consultations.index'))->assertOk();
        $this->as()->get(route('consultations.create', ['pet_id' => $this->pet->id]))->assertOk();
        $this->as()->get(route('consultations.edit', $consultation))->assertOk()->assertSee($this->pet->name);
        $this->as()->get(route('profile.professional'))->assertOk();
    }
}
