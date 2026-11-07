<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\LabAttachment;
use App\Models\LabRequest;
use App\Models\LabStudyType;
use App\Models\Pet;
use App\Support\Staff;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Solicitudes de estudios, resultados y seguimiento (§6.1, §6.3, §6.4). */
class LabRequestController extends Controller
{
    public function index()
    {
        $requests = LabRequest::with(['pet.client', 'pet.species', 'studyType', 'requester'])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('lab_study_type_id'), fn ($q, $id) => $q->where('lab_study_type_id', $id))
            ->when(request('pet_id'), fn ($q, $id) => $q->where('pet_id', $id))
            ->when(request('q'), fn ($q, $term) => $q->whereHas('pet', fn ($p) => $p->search($term)))
            ->when(request('from'), fn ($q, $from) => $q->where('requested_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when(request('to'), fn ($q, $to) => $q->where('requested_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('requested_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.lab.index', [
            'requests' => $requests,
            'studyTypes' => LabStudyType::orderBy('name')->get(['id', 'name']),
            'counts' => LabRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create()
    {
        $pet = request('pet_id') ? Pet::with('client')->find(request('pet_id')) : null;

        return view('admin.lab.create', [
            'selectedClient' => $pet?->client ?? (request('client_id') ? Client::find(request('client_id')) : null),
            'selectedPetId' => $pet?->id,
            'consultationId' => request('consultation_id'),
            'studyTypes' => LabStudyType::where('active', true)->orderBy('category')->orderBy('name')->get()->groupBy('category'),
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
        ]);
    }

    /** Una solicitud por cada estudio elegido, cada una con su propio seguimiento. */
    public function store()
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'pet_id' => [
                'required',
                Rule::exists('pets', 'id')->where('company_id', $companyId)->where('client_id', request('client_id'))->whereNull('deleted_at'),
            ],
            'consultation_id' => ['nullable', Rule::exists('consultations', 'id')->where('company_id', $companyId)->where('pet_id', request('pet_id'))],
            'study_types' => 'required|array|min:1',
            'study_types.*' => [Rule::exists('lab_study_types', 'id')->where('company_id', $companyId)->where('active', true)],
            'requested_by' => ['nullable', 'integer'],
            'requested_at' => 'required|date|before_or_equal:today',
            'external_lab' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:2000',
        ], [
            'pet_id.exists' => 'La mascota no pertenece al propietario elegido.',
            'consultation_id.exists' => 'La consulta no corresponde a esta mascota.',
            'study_types.required' => 'Elige al menos un estudio.',
        ], ['client_id' => 'propietario', 'pet_id' => 'mascota', 'requested_at' => 'fecha de solicitud']);

        $this->ensureProfessional($validated['requested_by'] ?? null, 'requested_by');

        $created = DB::transaction(fn () => collect($validated['study_types'])->unique()->map(fn ($typeId) => LabRequest::create([
            'pet_id' => $validated['pet_id'],
            'consultation_id' => $validated['consultation_id'] ?? null,
            'lab_study_type_id' => $typeId,
            'requested_by' => $validated['requested_by'] ?? auth()->id(),
            // La fecha elegida, con la hora del registro para ordenar el historial.
            'requested_at' => Carbon::parse($validated['requested_at'])->setTimeFrom(now()),
            'status' => 'requested',
            'external_lab' => $validated['external_lab'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])));

        return $created->count() === 1
            ? redirect()->route('lab.show', $created->first())->with('success', 'Estudio solicitado.')
            : redirect()->route('lab.index', ['pet_id' => $validated['pet_id']])->with('success', "{$created->count()} estudios solicitados.");
    }

    public function show(LabRequest $labRequest)
    {
        $labRequest->load(['pet.client', 'pet.species', 'pet.breed', 'consultation', 'studyType.parameters',
            'requester', 'responsible', 'results', 'attachments', 'sale']);

        return view('admin.lab.show', [
            'labRequest' => $labRequest,
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            // Al primer registro, las filas salen de los parámetros del estudio.
            'rows' => $labRequest->results->isNotEmpty()
                ? $labRequest->results->map->only(['name', 'value', 'unit', 'reference_min', 'reference_max', 'reference_text'])->values()->all()
                : $labRequest->studyType->parameters->map(fn ($p) => $p->only(['name', 'unit', 'reference_min', 'reference_max', 'reference_text']) + ['value' => null])->values()->all(),
        ]);
    }

    /** Guarda el borrador de resultados; con `finish` además completa el estudio. */
    public function saveResults(LabRequest $labRequest)
    {
        $this->ensureEditable($labRequest);

        // Las filas sin nombre son filas agregadas y dejadas en blanco.
        request()->merge(['results' => collect(request('results', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))->values()->all()]);

        $validated = request()->validate([
            'result_date' => 'nullable|date|before_or_equal:today',
            'responsible_id' => ['nullable', 'integer'],
            'result_notes' => 'nullable|string|max:5000',
            'results' => 'array',
            'results.*.name' => 'required|string|max:80',
            'results.*.value' => 'nullable|string|max:120',
            'results.*.unit' => 'nullable|string|max:30',
            'results.*.reference_min' => 'nullable|numeric',
            'results.*.reference_max' => 'nullable|numeric',
            'results.*.reference_text' => 'nullable|string|max:120',
        ], [], ['result_date' => 'fecha de resultado', 'result_notes' => 'observaciones']);

        $this->ensureProfessional($validated['responsible_id'] ?? null, 'responsible_id');

        $finishing = request()->boolean('finish');
        $rows = $validated['results'] ?? [];

        // Un estudio de imagen se informa con texto o archivos; los demás, con valores.
        if ($finishing && blank($validated['result_notes'] ?? null) && ! $labRequest->attachments()->exists()
            && ! collect($rows)->contains(fn ($row) => filled($row['value'] ?? null))) {
            throw ValidationException::withMessages(['results' => 'Registra al menos un valor, una observación o un archivo para completar el estudio.']);
        }

        DB::transaction(function () use ($labRequest, $validated, $rows, $finishing) {
            $labRequest->fill([
                'result_date' => $validated['result_date'] ?? ($finishing ? now()->toDateString() : null),
                'responsible_id' => $validated['responsible_id'] ?? ($finishing ? auth()->id() : null),
                'result_notes' => $validated['result_notes'] ?? null,
            ]);

            $labRequest->results()->reorder()->delete();
            foreach (array_values($rows) as $sort => $row) {
                $labRequest->results()->create($row + ['sort' => $sort]);
            }

            // Cargar resultados implica que el estudio ya está en marcha.
            if ($labRequest->status === 'requested') {
                $labRequest->transition('start');
            }

            $finishing ? $labRequest->transition('complete') : $labRequest->save();
        });

        return redirect()->route('lab.show', $labRequest)->with('success', $finishing ? 'Estudio completado.' : 'Resultados guardados.');
    }

    /** En proceso, entregado o anulado. */
    public function transition(LabRequest $labRequest, string $action)
    {
        abort_unless(in_array($action, ['start', 'deliver', 'cancel'], true), 404);

        $reason = $action === 'cancel'
            ? request()->validate(['cancel_reason' => 'required|string|max:255'], [], ['cancel_reason' => 'motivo'])['cancel_reason']
            : null;

        if (! $labRequest->transition($action, $reason)) {
            return back()->withErrors(['error' => "Esta acción no es posible en un estudio {$labRequest->status_label}."]);
        }

        return back()->with('success', "Estudio: {$labRequest->status_label}.");
    }

    public function storeAttachment(LabRequest $labRequest)
    {
        $this->ensureEditable($labRequest);

        $validated = request()->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ], ['files.*.mimes' => 'Solo se admiten imágenes (JPG, PNG, WEBP) y PDF.', 'files.*.max' => 'Cada archivo puede pesar hasta 10 MB.']);

        foreach ($validated['files'] as $file) {
            $labRequest->attachments()->create([
                'path' => $file->store("lab/{$labRequest->company_id}/{$labRequest->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Archivos adjuntados.');
    }

    /** Sirve un archivo del disco privado; el binding ya acota a la veterinaria activa. */
    public function downloadAttachment(LabRequest $labRequest, LabAttachment $attachment)
    {
        abort_unless($attachment->lab_request_id === $labRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyAttachment(LabRequest $labRequest, LabAttachment $attachment)
    {
        abort_unless($attachment->lab_request_id === $labRequest->id, 404);
        $this->ensureEditable($labRequest);

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Archivo eliminado.');
    }

    /** Informe imprimible (el navegador lo guarda como PDF). */
    public function report(LabRequest $labRequest)
    {
        abort_unless(in_array($labRequest->status, ['completed', 'delivered'], true), 404);

        $labRequest->load(['pet.client', 'pet.species', 'pet.breed', 'studyType', 'requester', 'responsible', 'results', 'attachments']);

        return view('admin.lab.report', ['labRequest' => $labRequest, 'company' => app(Tenancy::class)->company()]);
    }

    /** Consultas de una mascota, para relacionar la solicitud (JSON). */
    public function petConsultations(Pet $pet)
    {
        return response()->json(Consultation::with('diagnoses')->where('pet_id', $pet->id)->latest('started_at')->limit(20)->get()
            ->map(fn (Consultation $c) => [
                'id' => $c->id,
                'label' => $c->started_at->format('d/m/Y') . ' · ' . ($c->primary_diagnosis?->description ?? $c->reason ?? 'Consulta'),
            ]));
    }

    protected function ensureEditable(LabRequest $labRequest): void
    {
        if (! $labRequest->isEditable()) {
            throw ValidationException::withMessages(['error' => 'El estudio ya está cerrado: para corregirlo, anúlalo y solicítalo de nuevo.']);
        }
    }

    protected function ensureProfessional(?int $userId, string $field): void
    {
        if ($userId && ! Staff::belongsTo($userId, app(Tenancy::class)->company())) {
            throw ValidationException::withMessages([$field => 'El profesional no pertenece a esta veterinaria.']);
        }
    }
}
