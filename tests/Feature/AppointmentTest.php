<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Support\ClinicalCatalog;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agenda, estados de cita y sala de espera: gating por plan, choques de
 * horario del profesional y aislamiento entre veterinarias.
 */
class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
        'clients.view', 'pets.view',
    ];

    private Company $company;

    private User $user;

    private User $vet;

    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $this->user = $this->userInCompany($this->company, self::ALL, 'recepcion');
        $this->vet = $this->userInCompany($this->company, [], 'veterinarian');
        $this->pet = Pet::factory()->create([
            'client_id' => Client::factory()->create(['company_id' => $this->company->id])->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->pet->client_id,
            'pet_id' => $this->pet->id,
            'veterinarian_id' => $this->vet->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '10:00',
            'duration_minutes' => 30,
            'reason' => 'Control',
        ], $overrides);
    }

    private function agendar(array $overrides = [])
    {
        return $this->actingInCompany($this->user, $this->company)->post(route('appointments.store'), $this->payload($overrides));
    }

    public function test_sin_el_modulo_agenda_en_el_plan_no_entra(): void
    {
        $sinAgenda = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($sinAgenda, self::ALL, 'otro');

        $this->actingInCompany($user, $sinAgenda)->get(route('appointments.index'))->assertForbidden();
        $this->actingInCompany($user, $sinAgenda)->get(route('waiting-room.index'))->assertForbidden();
    }

    public function test_agenda_una_cita(): void
    {
        $this->agendar(['confirmed' => 1])->assertSessionHasNoErrors();

        $appointment = Appointment::allCompanies()->firstOrFail();
        $this->assertSame($this->company->id, $appointment->company_id);
        $this->assertSame('confirmed', $appointment->status);
        $this->assertSame('10:00', $appointment->starts_at->format('H:i'));
        $this->assertSame('10:30', $appointment->ends_at->format('H:i'));
    }

    public function test_el_profesional_no_puede_tener_dos_citas_a_la_vez(): void
    {
        $this->agendar()->assertSessionHasNoErrors();

        // 10:15 choca con 10:00–10:30.
        $this->agendar(['time' => '10:15'])->assertSessionHasErrors('time');

        // Justo a continuación sí cabe, y otro profesional también.
        $this->agendar(['time' => '10:30'])->assertSessionHasNoErrors();
        $otro = $this->userInCompany($this->company, [], 'veterinarian');
        $this->agendar(['time' => '10:15', 'veterinarian_id' => $otro->id])->assertSessionHasNoErrors();
    }

    public function test_una_cita_cancelada_libera_el_horario(): void
    {
        $this->agendar();
        Appointment::allCompanies()->first()->update(['status' => 'cancelled']);

        $this->agendar()->assertSessionHasNoErrors();
    }

    public function test_no_acepta_profesional_ni_mascota_ajenos(): void
    {
        $ajena = $this->companyWithPlan();
        $vetAjeno = $this->userInCompany($ajena, [], 'veterinarian');
        $otraMascota = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);

        $this->agendar(['veterinarian_id' => $vetAjeno->id])->assertSessionHasErrors('veterinarian_id');
        // La mascota existe en la veterinaria, pero es de otro propietario.
        $this->agendar(['pet_id' => $otraMascota->id])->assertSessionHasErrors('pet_id');

        $this->assertSame(0, Appointment::allCompanies()->count());
    }

    public function test_flujo_de_estados_de_la_atencion(): void
    {
        $appointment = Appointment::factory()->create(['pet_id' => $this->pet->id]);
        $as = $this->actingInCompany($this->user, $this->company);

        // No se puede finalizar algo que no empezó.
        $as->post(route('appointments.transition', [$appointment, 'finish']))->assertSessionHasErrors('error');

        $as->post(route('appointments.transition', [$appointment, 'check_in']));
        $this->assertSame('waiting', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->checked_in_at);

        // Sin profesional asignado, la atención la toma quien la inicia.
        $as->post(route('appointments.transition', [$appointment, 'start']));
        $this->assertSame('in_progress', $appointment->fresh()->status);
        $this->assertSame($this->user->id, $appointment->fresh()->veterinarian_id);

        $as->post(route('appointments.transition', [$appointment, 'finish']));
        $this->assertSame('attended', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->finished_at);

        // Una cita cerrada ya no se edita.
        $as->get(route('appointments.edit', $appointment))->assertRedirect(route('appointments.show', $appointment));
    }

    public function test_cancelar_guarda_el_motivo(): void
    {
        $appointment = Appointment::factory()->create(['pet_id' => $this->pet->id]);

        $this->actingInCompany($this->user, $this->company)
            ->post(route('appointments.transition', [$appointment, 'cancel']), ['cancellation_reason' => 'Viaje del dueño']);

        $this->assertSame('cancelled', $appointment->fresh()->status);
        $this->assertSame('Viaje del dueño', $appointment->fresh()->cancellation_reason);
    }

    public function test_el_calendario_solo_trae_citas_de_la_veterinaria_activa(): void
    {
        $mia = Appointment::factory()->create(['pet_id' => $this->pet->id]);
        $ajena = $this->companyWithPlan();
        Appointment::factory()->create([
            'pet_id' => Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $ajena->id])->id])->id,
        ]);

        $this->actingInCompany($this->user, $this->company)
            ->getJson(route('appointments.events', ['start' => now()->toDateString(), 'end' => now()->addWeek()->toDateString()]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mia->id);
    }

    public function test_arrastrar_reprograma_y_respeta_los_choques(): void
    {
        $day = now()->addDays(2)->toDateString();
        $a = Appointment::factory()->create(['pet_id' => $this->pet->id, 'veterinarian_id' => $this->vet->id,
            'starts_at' => "$day 09:00", 'ends_at' => "$day 09:30"]);
        Appointment::factory()->create(['pet_id' => $this->pet->id, 'veterinarian_id' => $this->vet->id,
            'starts_at' => "$day 11:00", 'ends_at' => "$day 11:30"]);
        $as = $this->actingInCompany($this->user, $this->company);

        // El calendario manda la hora local con sufijo Z (modo UTC): no debe desplazarse.
        $as->patchJson(route('appointments.move', $a), ['starts_at' => "{$day}T14:00:00Z", 'ends_at' => "{$day}T14:30:00Z"])->assertOk();
        $this->assertSame('14:00', $a->fresh()->starts_at->format('H:i'));

        $as->patchJson(route('appointments.move', $a), ['starts_at' => "{$day}T11:15:00", 'ends_at' => "{$day}T11:45:00"])->assertStatus(422);
        $this->assertSame('14:00', $a->fresh()->starts_at->format('H:i'));
    }

    public function test_paciente_sin_cita_entra_directo_a_la_espera(): void
    {
        $this->actingInCompany($this->user, $this->company)->post(route('waiting-room.walk-in'), [
            'client_id' => $this->pet->client_id,
            'pet_id' => $this->pet->id,
            'reason' => 'Herida en la pata',
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::allCompanies()->firstOrFail();
        $this->assertSame('waiting', $appointment->status);
        $this->assertTrue($appointment->is_walk_in);
        $this->assertNotNull($appointment->checked_in_at);

        app(Tenancy::class)->forget();
        $this->actingInCompany($this->user, $this->company)->get(route('waiting-room.index'))
            ->assertOk()->assertSee($this->pet->name)->assertSee('Herida en la pata');
    }

    public function test_el_catalogo_base_trae_servicios(): void
    {
        ClinicalCatalog::seedFor($this->company);
        ClinicalCatalog::seedFor($this->company);

        $this->assertSame(count(ClinicalCatalog::SERVICES), Service::forCompany($this->company)->count());
    }

    public function test_las_pantallas_de_la_agenda_cargan(): void
    {
        $appointment = Appointment::factory()->create(['pet_id' => $this->pet->id, 'veterinarian_id' => $this->vet->id]);
        $as = $this->actingInCompany($this->user, $this->company);

        $as->get(route('appointments.index'))->assertOk();
        $as->get(route('appointments.create', ['pet_id' => $this->pet->id]))->assertOk()->assertSee($this->pet->client->full_name);
        $as->get(route('appointments.show', $appointment))->assertOk()->assertSee($this->pet->name);
        $as->get(route('appointments.edit', $appointment))->assertOk();
        $as->getJson(route('clients.pets', $this->pet->client_id))->assertOk()->assertJsonPath('0.id', $this->pet->id);
    }
}
