<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\ConsultationFollowup;
use App\Models\Pet;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Support\Stock;
use App\Support\Staff;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Consultas veterinarias (§4): evaluación clínica, diagnóstico, receta,
 * procedimientos y seguimiento. Las rutas exigen plan:consultas.
 */
class ConsultationController extends Controller
{
    public function index()
    {
        $consultations = Consultation::with(['pet.species', 'client', 'veterinarian', 'diagnoses'])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('veterinarian_id'), fn ($q, $id) => $q->where('veterinarian_id', $id))
            ->when(request('from'), fn ($q, $from) => $q->where('started_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when(request('to'), fn ($q, $to) => $q->where('started_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when(request('q'), fn ($q, $term) => $q->whereHas('pet', fn ($p) => $p->search($term)))
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString();

        // Pacientes que ya llegaron (o están en atención) y aún no tienen consulta.
        $company = app(Tenancy::class)->company();
        $pending = $company?->planAllows('agenda')
            ? Appointment::with(['pet', 'client', 'veterinarian'])
                ->onDate(now())
                ->whereIn('status', ['waiting', 'in_progress'])
                ->whereDoesntHave('consultation')
                ->orderBy('checked_in_at')
                ->get()
            : collect();

        return view('admin.consultations.index', [
            'consultations' => $consultations,
            'pending' => $pending,
            'professionals' => Staff::professionals($company),
        ]);
    }

    public function create()
    {
        $pet = request('pet_id') ? Pet::with('client')->find(request('pet_id')) : null;

        return view('admin.consultations.create', [
            'selectedClient' => $pet?->client ?? (request('client_id') ? Client::find(request('client_id')) : null),
            'selectedPetId' => $pet?->id,
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
        ]);
    }

    /** Abre una consulta sin cita previa y lleva al formulario clínico. */
    public function store()
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'pet_id' => [
                'required',
                Rule::exists('pets', 'id')->where('company_id', $companyId)->where('client_id', request('client_id'))->whereNull('deleted_at'),
            ],
            'veterinarian_id' => ['nullable', 'integer'],
            'reason' => 'nullable|string|max:255',
        ], ['pet_id.exists' => 'La mascota no pertenece al propietario elegido.'], [
            'client_id' => 'propietario',
            'pet_id' => 'mascota',
        ]);

        $this->ensureProfessional($validated['veterinarian_id'] ?? null, 'veterinarian_id');

        $consultation = Consultation::create($validated + [
            'veterinarian_id' => $validated['veterinarian_id'] ?? auth()->id(),
            'status' => 'in_progress',
            'started_at' => now(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('consultations.edit', $consultation);
    }

    /** "Iniciar consulta" desde una cita o la sala de espera. */
    public function startFromAppointment(Appointment $appointment)
    {
        if ($existing = $appointment->consultation) {
            return redirect()->route($existing->isOpen() ? 'consultations.edit' : 'consultations.show', $existing);
        }

        if (! in_array($appointment->status, ['pending', 'confirmed', 'waiting', 'in_progress'], true)) {
            return back()->withErrors(['error' => "No se puede iniciar una consulta de una cita {$appointment->status_label}."]);
        }

        $consultation = DB::transaction(function () use ($appointment) {
            if ($appointment->status !== 'in_progress') {
                $appointment->veterinarian_id ??= auth()->id();
                $appointment->transition('start');
            }

            return Consultation::create([
                'branch_id' => $appointment->branch_id,
                'pet_id' => $appointment->pet_id,
                'client_id' => $appointment->client_id,
                'appointment_id' => $appointment->id,
                'veterinarian_id' => $appointment->veterinarian_id ?? auth()->id(),
                'status' => 'in_progress',
                'started_at' => now(),
                'reason' => $appointment->reason,
                'created_by' => auth()->id(),
            ]);
        });

        return redirect()->route('consultations.edit', $consultation);
    }

    public function show(Consultation $consultation)
    {
        $consultation->load([
            'pet.species', 'pet.breed', 'client', 'veterinarian', 'appointment', 'diagnoses',
            'prescriptionItems.product', 'procedures.performer', 'followups.recorder', 'supplies.product', 'labRequests.studyType',
        ]);

        return view('admin.consultations.show', compact('consultation'));
    }

    public function edit(Consultation $consultation)
    {
        if (! $consultation->isOpen()) {
            return redirect()->route('consultations.show', $consultation);
        }

        $consultation->load(['pet.species', 'pet.breed', 'client', 'diagnoses', 'prescriptionItems.product', 'procedures', 'supplies.product']);

        return view('admin.consultations.edit', [
            'consultation' => $consultation,
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            // Sugerencias a partir de lo que la veterinaria ya usó.
            'diagnosisSuggestions' => ConsultationDiagnosis::query()->distinct()->orderBy('description')->limit(300)->pluck('description'),
            'medicationSuggestions' => PrescriptionItem::query()->distinct()->orderBy('medication')->limit(300)->pluck('medication'),
            'previousConsultations' => $consultation->pet->consultations()
                ->whereKeyNot($consultation->id)->with('diagnoses')->limit(5)->get(),
            // Con inventario: los medicamentos e insumos se vinculan a productos.
            'inventoryProducts' => app(Tenancy::class)->company()?->planAllows('inventario')
                ? Product::where('active', true)->orderBy('name')->get(['id', 'name', 'presentation', 'unit'])
                : collect(),
        ]);
    }

    /** Guarda el borrador; con `finish` además cierra la consulta. */
    public function update(Consultation $consultation)
    {
        if (! $consultation->isOpen()) {
            return redirect()->route('consultations.show', $consultation)
                ->withErrors(['error' => 'La consulta está finalizada: registra un seguimiento en su lugar.']);
        }

        $this->dropEmptyRows('diagnoses', 'description');
        $this->dropEmptyRows('prescription', 'medication');
        $this->dropEmptyRows('procedures', 'name');
        $this->dropEmptyRows('supplies', 'product_id');
        $productRule = Rule::exists('products', 'id')->where('company_id', app(Tenancy::class)->id())->whereNull('deleted_at');

        $validated = request()->validate([
            'veterinarian_id' => ['nullable', 'integer'],
            'reason' => 'nullable|string|max:255',
            'anamnesis' => 'nullable|string|max:10000',
            'physical_exam' => 'nullable|string|max:10000',
            'temperature' => 'nullable|numeric|min:25|max:45',
            'weight' => 'nullable|numeric|min:0.01|max:9999',
            'heart_rate' => 'nullable|integer|min:1|max:400',
            'respiratory_rate' => 'nullable|integer|min:1|max:300',
            'hydration' => ['nullable', Rule::in(array_keys(Consultation::HYDRATION))],
            'body_condition' => ['nullable', 'integer', Rule::in(array_keys(Consultation::BODY_CONDITION))],
            'mucous_membranes' => ['nullable', Rule::in(array_keys(Consultation::MUCOUS_MEMBRANES))],
            'clinical_notes' => 'nullable|string|max:10000',
            'instructions' => 'nullable|string|max:5000',
            'recommendations' => 'nullable|string|max:5000',
            'next_review_at' => 'nullable|date|after_or_equal:today',

            'diagnoses' => 'array',
            'diagnoses.*.type' => ['required', Rule::in(array_keys(ConsultationDiagnosis::TYPES))],
            'diagnoses.*.code' => 'nullable|string|max:20',
            'diagnoses.*.description' => 'required|string|max:255',
            'diagnoses.*.notes' => 'nullable|string|max:255',

            'prescription' => 'array',
            'prescription.*.medication' => 'required|string|max:255',
            'prescription.*.presentation' => 'nullable|string|max:255',
            'prescription.*.dose' => 'required|string|max:60',
            'prescription.*.route' => ['required', Rule::in(array_keys(PrescriptionItem::ROUTES))],
            'prescription.*.frequency' => 'required|string|max:60',
            'prescription.*.duration' => 'nullable|string|max:60',
            'prescription.*.quantity' => 'nullable|string|max:40',
            'prescription.*.instructions' => 'nullable|string|max:255',
            'prescription.*.product_id' => ['nullable', $productRule],

            'procedures' => 'array',
            'procedures.*.name' => 'required|string|max:255',
            'procedures.*.description' => 'nullable|string|max:5000',
            'procedures.*.materials' => 'nullable|string|max:2000',
            'procedures.*.medications' => 'nullable|string|max:2000',
            'procedures.*.performed_by' => 'nullable|integer',
            'procedures.*.cost' => 'nullable|numeric|min:0|max:9999999',

            'supplies' => 'array',
            'supplies.*.product_id' => ['required', $productRule],
            'supplies.*.quantity' => 'required|numeric|min:0.01|max:99999',
            'supplies.*.notes' => 'nullable|string|max:255',
        ], [
            'diagnoses.*.description.required' => 'Cada diagnóstico necesita una descripción.',
            'prescription.*.dose.required' => 'Indica la dosis de cada medicamento.',
            'prescription.*.frequency.required' => 'Indica la frecuencia de cada medicamento.',
            'next_review_at.after_or_equal' => 'La próxima revisión no puede ser en el pasado.',
        ], [
            'temperature' => 'temperatura',
            'weight' => 'peso',
            'heart_rate' => 'frecuencia cardíaca',
            'respiratory_rate' => 'frecuencia respiratoria',
        ]);

        $this->ensureProfessional($validated['veterinarian_id'] ?? null, 'veterinarian_id');
        foreach ($validated['procedures'] ?? [] as $i => $procedure) {
            $this->ensureProfessional($procedure['performed_by'] ?? null, "procedures.$i.performed_by");
        }

        $finishing = request()->boolean('finish');
        if ($finishing && ! collect($validated['diagnoses'] ?? [])->contains('type', 'primary')) {
            throw ValidationException::withMessages(['diagnoses' => 'Para finalizar la consulta registra al menos un diagnóstico principal.']);
        }

        DB::transaction(function () use ($consultation, $validated, $finishing) {
            $consultation->update(collect($validated)->except(['diagnoses', 'prescription', 'procedures', 'supplies'])->all());

            $this->syncRows($consultation->diagnoses(), $validated['diagnoses'] ?? []);
            $this->syncRows($consultation->prescriptionItems(), $validated['prescription'] ?? []);
            $this->syncRows($consultation->procedures(), collect($validated['procedures'] ?? [])
                ->map(fn ($p) => $p + ['cost' => $p['cost'] ?? 0])->all());
            $this->syncRows($consultation->supplies(), $validated['supplies'] ?? []);

            if ($finishing) {
                $this->finish($consultation);
            }
        });

        return $finishing
            ? redirect()->route('consultations.show', $consultation)->with('success', 'Consulta finalizada.')
            : redirect()->route('consultations.edit', $consultation)->with('success', 'Borrador guardado.');
    }

    public function storeFollowup(Consultation $consultation)
    {
        $validated = request()->validate([
            'treatment_status' => ['required', Rule::in(array_keys(ConsultationFollowup::TREATMENT_STATUSES))],
            'notes' => 'required|string|max:5000',
        ], [], ['notes' => 'evolución']);

        $consultation->followups()->create($validated + [
            'recorded_at' => now(),
            'recorded_by' => auth()->id(),
        ]);

        return redirect()->route('consultations.show', $consultation)->with('success', 'Seguimiento registrado.');
    }

    /** Receta imprimible (el navegador la guarda como PDF). */
    public function prescription(Consultation $consultation)
    {
        $consultation->load(['pet.species', 'pet.breed', 'client', 'veterinarian', 'prescriptionItems', 'diagnoses']);

        return view('admin.consultations.prescription', [
            'consultation' => $consultation,
            'company' => app(Tenancy::class)->company(),
        ]);
    }

    /** Entrega al propietario un medicamento de la receta y lo descuenta del stock. */
    public function dispense(Consultation $consultation, PrescriptionItem $item)
    {
        abort_unless($item->consultation_id === $consultation->id, 404);

        // Mientras la consulta está abierta la receta se reescribe al guardar.
        if ($consultation->isOpen()) {
            return back()->withErrors(['error' => 'Finaliza la consulta antes de dispensar la receta.']);
        }

        if (! $item->product_id || $item->dispensed_at) {
            return back()->withErrors(['error' => 'Este medicamento no está vinculado al inventario o ya fue dispensado.']);
        }

        $quantity = (float) request()->validate([
            'quantity' => 'required|numeric|min:0.01|max:99999',
        ], [], ['quantity' => 'cantidad'])['quantity'];

        // A diferencia de un insumo ya usado, no se entrega lo que no hay.
        Stock::consume($item->product, $consultation->branch ?? Stock::defaultBranch(), $quantity, 'prescription', [
            'source' => $item,
            'notes' => "Receta {$consultation->prescription_number} · {$consultation->pet?->name}",
        ]);

        $item->update(['dispensed_quantity' => $quantity, 'dispensed_at' => now()]);

        return back()->with('success', "Dispensado: {$item->medication}.");
    }

    public function destroy(Consultation $consultation)
    {
        // Solo se descartan borradores: una consulta cerrada es historia clínica.
        if (! $consultation->isOpen()) {
            return back()->withErrors(['error' => 'Una consulta finalizada no se puede eliminar.']);
        }

        $consultation->delete();

        return redirect()->route('consultations.index')->with('success', 'Borrador de consulta descartado.');
    }

    protected function finish(Consultation $consultation): void
    {
        $consultation->update(['status' => 'finished', 'finished_at' => now()]);

        // El peso medido en consulta pasa al historial de la mascota.
        if ($consultation->weight !== null) {
            $consultation->pet->weights()->create([
                'weight' => $consultation->weight,
                'recorded_at' => $consultation->started_at->toDateString(),
                'notes' => "Consulta #{$consultation->id}",
                'recorded_by' => auth()->id(),
            ]);
            $consultation->pet->syncCurrentWeight();
        }

        // Los insumos usados salen del inventario. Ya se usaron: si falta
        // stock la salida se registra igual y la existencia queda en negativo.
        if (app(Tenancy::class)->company()?->planAllows('inventario')) {
            $branch = $consultation->branch ?? Stock::defaultBranch();
            foreach ($consultation->supplies()->with('product')->get() as $supply) {
                Stock::consume($supply->product, $branch, (float) $supply->quantity, 'procedure', [
                    'source' => $consultation,
                    'allow_negative' => true,
                    'notes' => "Consulta #{$consultation->id} · {$consultation->pet?->name}",
                ]);
            }
        }

        // La cita que originó la consulta queda atendida.
        $appointment = $consultation->appointment;
        if ($appointment?->status === 'in_progress') {
            $appointment->transition('finish');
        }
    }

    /** Reemplaza las filas hijas por las enviadas, conservando el orden. */
    protected function syncRows($relation, array $rows): void
    {
        $relation->reorder()->delete();

        foreach (array_values($rows) as $sort => $row) {
            $relation->create($row + ['sort' => $sort]);
        }
    }

    /** Quita del request las filas que el usuario dejó en blanco. */
    protected function dropEmptyRows(string $key, string $field): void
    {
        $rows = collect(request()->input($key, []))
            ->filter(fn ($row) => is_array($row) && filled($row[$field] ?? null))
            ->values()
            ->all();

        request()->merge([$key => $rows]);
    }

    protected function ensureProfessional(?int $userId, string $field): void
    {
        if ($userId && ! Staff::belongsTo($userId, app(Tenancy::class)->company())) {
            throw ValidationException::withMessages([$field => 'El profesional no pertenece a esta veterinaria.']);
        }
    }
}
