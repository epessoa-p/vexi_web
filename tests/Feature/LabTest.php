<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\LabAttachment;
use App\Models\LabRequest;
use App\Models\LabResult;
use App\Models\LabStudyType;
use App\Models\Pet;
use App\Models\User;
use App\Support\ClinicalCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Laboratorio: solicitudes, resultados con referencia propia, bloqueo al
 * completar, archivos privados y aislamiento entre veterinarias.
 */
class LabTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = [
        'lab_requests.view', 'lab_requests.create', 'lab_requests.edit', 'lab_results.edit',
        'lab_studies.view', 'lab_studies.create', 'lab_studies.edit', 'lab_studies.delete', 'pets.view', 'consultations.view',
    ];

    private Company $company;

    private User $vet;

    private Pet $pet;

    private LabStudyType $study;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['laboratorio', 'consultas']]);
        $this->vet = $this->userInCompany($this->company, self::ALL, 'veterinarian');
        $this->pet = Pet::factory()->create(['client_id' => Client::factory()->create(['company_id' => $this->company->id])->id]);
        $this->study = LabStudyType::factory()->create(['company_id' => $this->company->id, 'name' => 'Hemograma']);
        $this->study->parameters()->create(['company_id' => $this->company->id, 'name' => 'Hematocrito', 'unit' => '%', 'reference_min' => 37, 'reference_max' => 55]);
    }

    private function as()
    {
        return $this->actingInCompany($this->vet, $this->company);
    }

    private function labRequest(array $attributes = []): LabRequest
    {
        return LabRequest::factory()->create(['pet_id' => $this->pet->id, 'lab_study_type_id' => $this->study->id] + $attributes);
    }

    /** @return array<string, mixed> */
    private function resultData(array $overrides = []): array
    {
        return array_merge([
            'result_notes' => 'Sin novedad',
            'results' => [['name' => 'Hematocrito', 'value' => '60', 'unit' => '%', 'reference_min' => 37, 'reference_max' => 55]],
        ], $overrides);
    }

    public function test_sin_el_modulo_laboratorio_no_entra(): void
    {
        $sin = $this->companyWithPlan(planAttributes: ['features' => ['consultas']]);
        $user = $this->userInCompany($sin, self::ALL, 'otro');

        $this->actingInCompany($user, $sin)->get(route('lab.index'))->assertForbidden();
        $this->actingInCompany($user, $sin)->get(route('lab-studies.index'))->assertForbidden();
    }

    public function test_crea_una_solicitud_por_estudio_ligada_a_la_consulta(): void
    {
        $orina = LabStudyType::factory()->create(['company_id' => $this->company->id]);
        $consultation = Consultation::factory()->create(['pet_id' => $this->pet->id]);

        $this->as()->post(route('lab.store'), [
            'client_id' => $this->pet->client_id,
            'pet_id' => $this->pet->id,
            'consultation_id' => $consultation->id,
            'study_types' => [$this->study->id, $orina->id],
            'requested_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $requests = LabRequest::allCompanies()->get();
        $this->assertCount(2, $requests);
        $this->assertSame([$consultation->id], $requests->pluck('consultation_id')->unique()->values()->all());
        $this->assertSame($this->vet->id, $requests->first()->requested_by);
        $this->assertSame('requested', $requests->first()->status);
    }

    public function test_rechaza_estudio_mascota_consulta_y_profesional_ajenos(): void
    {
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['laboratorio']]);
        $estudioAjeno = LabStudyType::factory()->create(['company_id' => $ajena->id]);
        $vetAjeno = $this->userInCompany($ajena, [], 'veterinarian');
        $otraMascota = Pet::factory()->create(['client_id' => $this->pet->client_id]);
        $consultaDeOtra = Consultation::factory()->create(['pet_id' => $otraMascota->id]);
        $base = ['client_id' => $this->pet->client_id, 'pet_id' => $this->pet->id, 'study_types' => [$this->study->id], 'requested_at' => now()->toDateString()];

        $this->as()->post(route('lab.store'), ['study_types' => [$estudioAjeno->id]] + $base)->assertSessionHasErrors('study_types.0');
        $this->as()->post(route('lab.store'), ['consultation_id' => $consultaDeOtra->id] + $base)->assertSessionHasErrors('consultation_id');
        $this->as()->post(route('lab.store'), ['requested_by' => $vetAjeno->id] + $base)->assertSessionHasErrors('requested_by');

        $this->assertSame(0, LabRequest::allCompanies()->count());
    }

    public function test_guardar_borrador_pasa_a_en_proceso_y_reemplaza_filas(): void
    {
        $labRequest = $this->labRequest();

        $this->as()->put(route('lab.results.save', $labRequest), $this->resultData())->assertSessionHasNoErrors();
        $this->as()->put(route('lab.results.save', $labRequest), $this->resultData(['results' => [
            ['name' => 'Hematocrito', 'value' => '40'],
            ['name' => '', 'value' => 'fila vacía'],
        ]]))->assertSessionHasNoErrors();

        $this->assertSame('in_process', $labRequest->fresh()->status);
        $this->assertNotNull($labRequest->fresh()->started_at);
        $this->assertSame(['40'], LabResult::allCompanies()->pluck('value')->all());
    }

    public function test_completar_exige_contenido_y_bloquea_el_resultado(): void
    {
        $labRequest = $this->labRequest();

        $this->as()->put(route('lab.results.save', $labRequest), ['finish' => 1, 'results' => [['name' => 'Hematocrito', 'value' => '']]])
            ->assertSessionHasErrors('results');
        $this->assertSame('requested', $labRequest->fresh()->status);

        $this->as()->put(route('lab.results.save', $labRequest), $this->resultData(['finish' => 1]))->assertSessionHasNoErrors();
        $completed = $labRequest->fresh();
        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame($this->vet->id, $completed->responsible_id);

        // Cerrado: no cambia ni admite archivos; se anula con motivo o se entrega.
        Storage::fake('local');
        $this->as()->put(route('lab.results.save', $labRequest), $this->resultData(['result_notes' => 'Cambiado']))->assertSessionHasErrors('error');
        $this->as()->post(route('lab.attachments.store', $labRequest), ['files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('error');
        $this->assertSame('Sin novedad', $labRequest->fresh()->result_notes);

        $this->as()->post(route('lab.transition', [$labRequest, 'deliver']))->assertSessionHasNoErrors();
        $this->assertSame('delivered', $labRequest->fresh()->status);
        // Entregado ya no se anula.
        $this->as()->post(route('lab.transition', [$labRequest, 'cancel']), ['cancel_reason' => 'Error'])->assertSessionHasErrors('error');
    }

    public function test_anular_exige_motivo(): void
    {
        $labRequest = $this->labRequest(['status' => 'completed']);

        $this->as()->post(route('lab.transition', [$labRequest, 'cancel']))->assertSessionHasErrors('cancel_reason');
        $this->as()->post(route('lab.transition', [$labRequest, 'cancel']), ['cancel_reason' => 'Valor mal cargado'])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $labRequest->fresh()->status);
        $this->assertSame('Valor mal cargado', $labRequest->fresh()->cancel_reason);
    }

    public function test_marca_valores_fuera_de_rango(): void
    {
        $flag = fn (string $value, ?float $min = 37, ?float $max = 55) => (new LabResult(['value' => $value, 'reference_min' => $min, 'reference_max' => $max]))->flag;

        $this->assertSame('high', $flag('60'));
        $this->assertSame('low', $flag('30,5'));
        $this->assertSame('normal', $flag('37'));
        $this->assertNull($flag('Negativo'));
        $this->assertNull($flag('40', null, null));
    }

    public function test_el_informe_conserva_la_referencia_aunque_cambie_el_catalogo(): void
    {
        $labRequest = $this->labRequest();
        $this->as()->put(route('lab.results.save', $labRequest), $this->resultData(['finish' => 1]));

        $this->as()->put(route('lab-studies.update', $this->study), ['name' => 'Hemograma', 'category' => 'hematology', 'active' => 1,
            'parameters' => [['name' => 'Hematocrito', 'reference_min' => 10, 'reference_max' => 20]]])->assertSessionHasNoErrors();

        $this->as()->get(route('lab.report', $labRequest))->assertOk()->assertSee('37 – 55')->assertSee('Alto')->assertDontSee('10 – 20');
    }

    public function test_el_informe_solo_existe_con_resultados_cerrados(): void
    {
        $this->as()->get(route('lab.report', $this->labRequest()))->assertNotFound();
    }

    public function test_los_archivos_son_privados_y_no_cruzan_veterinarias(): void
    {
        Storage::fake('local');
        $labRequest = $this->labRequest();

        $this->as()->post(route('lab.attachments.store', $labRequest), ['files' => [UploadedFile::fake()->image('placa.png')]])->assertSessionHasNoErrors();
        $this->as()->post(route('lab.attachments.store', $labRequest), ['files' => [UploadedFile::fake()->create('virus.html', 1, 'text/html')]])->assertSessionHasErrors('files.0');

        $attachment = LabAttachment::allCompanies()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->as()->get(route('lab.attachments.show', [$labRequest, $attachment]))->assertOk();

        // Sin sesión no hay acceso.
        $url = route('lab.attachments.show', [$labRequest, $attachment]);
        auth()->logout();
        $this->flushSession();
        $this->get($url)->assertRedirect(route('login'));

        // Otra veterinaria no lo ve.
        $ajena = $this->companyWithPlan(planAttributes: ['features' => ['laboratorio']]);
        $user = $this->userInCompany($ajena, self::ALL, 'otro');
        $this->actingInCompany($user, $ajena)->get($url)->assertNotFound();

        // Un adjunto de otra solicitud no se alcanza por esta ruta.
        $otra = $this->labRequest();
        $this->as()->get(route('lab.attachments.show', [$otra, $attachment]))->assertNotFound();

        $this->as()->delete(route('lab.attachments.destroy', [$labRequest, $attachment]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_permisos_de_resultados_separados_de_la_solicitud(): void
    {
        $labRequest = $this->labRequest();
        $recepcion = $this->userInCompany($this->company, ['lab_requests.view'], 'recepcion');

        $this->actingInCompany($recepcion, $this->company)->get(route('lab.show', $labRequest))->assertOk()->assertDontSee('Guardar y completar');
        $this->actingInCompany($recepcion, $this->company)->put(route('lab.results.save', $labRequest), $this->resultData())->assertForbidden();
        $this->actingInCompany($recepcion, $this->company)->post(route('lab.transition', [$labRequest, 'start']))->assertForbidden();
    }

    public function test_catalogo_base_sin_referencias_e_idempotente(): void
    {
        ClinicalCatalog::seedFor($this->company);
        ClinicalCatalog::seedFor($this->company);

        $this->assertSame(count(ClinicalCatalog::LAB_STUDIES), LabStudyType::forCompany($this->company)->count());
        // El Hemograma ya existía con su parámetro: no se pisa.
        $this->assertSame(1, $this->study->parameters()->allCompanies()->count());
        $bio = LabStudyType::forCompany($this->company)->where('name', 'Bioquímica sanguínea')->firstOrFail();
        $this->assertNull(\App\Models\LabStudyParameter::allCompanies()->where('lab_study_type_id', $bio->id)->first()->reference_min);
    }

    public function test_un_estudio_con_solicitudes_no_se_elimina(): void
    {
        $this->labRequest();

        $this->as()->delete(route('lab-studies.destroy', $this->study))->assertSessionHasErrors('error');
        $this->assertNotNull($this->study->fresh());
    }

    public function test_las_pantallas_cargan_y_el_expediente_lo_muestra(): void
    {
        $labRequest = $this->labRequest();
        $this->as()->put(route('lab.results.save', $labRequest), $this->resultData(['finish' => 1]));

        $as = $this->as();
        $as->get(route('lab.index'))->assertOk()->assertSee($labRequest->code);
        $as->get(route('lab.create', ['pet_id' => $this->pet->id]))->assertOk()->assertSee('Hemograma');
        $as->get(route('lab.show', $labRequest))->assertOk()->assertSee('Hematocrito');
        $as->get(route('lab-studies.index'))->assertOk();
        $as->get(route('lab-studies.create'))->assertOk();
        $as->get(route('lab-studies.edit', $this->study))->assertOk();
        $as->getJson(route('lab.pet-consultations', $this->pet))->assertOk();
        $as->get(route('pets.show', ['pet' => $this->pet, 'tab' => 'record']))->assertOk()->assertSee('Fuera de rango: Hematocrito');
    }
}
