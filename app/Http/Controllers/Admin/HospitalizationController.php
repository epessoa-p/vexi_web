<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\Hospitalization;
use App\Models\HospitalizationCharge;
use App\Models\HospitalizationDose;
use App\Models\HospitalizationRecord;
use App\Models\HospitalizationTreatment;
use App\Models\Kennel;
use App\Models\Pet;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Support\Hospitalizations;
use App\Support\Staff;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Hospitalización (§10): tablero, admisión, tratamientos, evolución, costos y alta. */
class HospitalizationController extends Controller
{
    public function index()
    {
        $status = array_key_exists((string) request('status'), Hospitalization::STATUSES) ? request('status') : 'active';

        $hospitalizations = Hospitalization::with(['pet.client', 'pet.species', 'kennel', 'veterinarian'])
            ->where('status', $status)
            ->when(request('q'), fn ($q, $term) => $q->whereHas('pet', fn ($p) => $p->search($term)))
            ->orderByDesc($status === 'active' ? 'admitted_at' : 'discharged_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Horario de administración: lo atrasado y las próximas 12 horas.
        $doses = $status === 'active'
            ? HospitalizationDose::with(['treatment.hospitalization.pet', 'treatment.hospitalization.kennel'])
                ->where('status', 'pending')
                ->where('scheduled_at', '<=', now()->addHours(12))
                ->whereHas('treatment.hospitalization', fn ($q) => $q->where('status', 'active'))
                ->orderBy('scheduled_at')
                ->limit(50)
                ->get()
            : collect();

        return view('admin.hospitalizations.index', [
            'hospitalizations' => $hospitalizations,
            'doses' => $doses,
            'status' => $status,
            'activeCount' => Hospitalization::where('status', 'active')->count(),
            'kennels' => Kennel::where('active', true)->orderBy('name')->get(),
            'occupied' => Hospitalization::where('status', 'active')->whereNotNull('kennel_id')->pluck('pet_id', 'kennel_id'),
        ]);
    }

    public function create()
    {
        $consultation = request('consultation_id') ? Consultation::with(['diagnoses', 'pet.client'])->find(request('consultation_id')) : null;
        $pet = $consultation?->pet ?? (request('pet_id') ? Pet::with('client')->find(request('pet_id')) : null);

        return view('admin.hospitalizations.create', [
            'consultation' => $consultation,
            'selectedClient' => $pet?->client ?? (request('client_id') ? Client::find(request('client_id')) : null),
            'selectedPetId' => $pet?->id,
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            'kennels' => $this->freeKennels(),
        ]);
    }

    public function store()
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'pet_id' => [
                'required',
                Rule::exists('pets', 'id')->where('company_id', $companyId)->where('client_id', request('client_id'))
                    ->where('status', 'active')->whereNull('deleted_at'),
            ],
            'consultation_id' => ['nullable', Rule::exists('consultations', 'id')->where('company_id', $companyId)->where('pet_id', request('pet_id'))],
            'kennel_id' => ['nullable', Rule::exists('kennels', 'id')->where('company_id', $companyId)->where('active', true)],
            'veterinarian_id' => ['nullable', 'integer'],
            'admitted_at' => 'required|date|before_or_equal:now',
            'condition' => ['required', Rule::in(array_keys(Hospitalization::CONDITIONS))],
            'reason' => 'required|string|max:255',
            'admission_diagnosis' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'daily_rate' => 'nullable|numeric|min:0|max:9999999',
        ], [
            'pet_id.exists' => 'La mascota no está activa o no pertenece al propietario elegido.',
            'consultation_id.exists' => 'La consulta no corresponde a esta mascota.',
        ], [
            'client_id' => 'propietario',
            'pet_id' => 'mascota',
            'admitted_at' => 'fecha de ingreso',
            'reason' => 'motivo',
        ]);

        $this->ensureProfessional($validated['veterinarian_id'] ?? null, 'veterinarian_id');

        $hospitalization = Hospitalizations::admit(collect($validated)->except('client_id')->all() + [
            'veterinarian_id' => $validated['veterinarian_id'] ?? auth()->id(),
            'daily_rate' => (float) ($validated['daily_rate'] ?? 0),
        ]);

        return redirect()->route('hospitalizations.show', $hospitalization)->with('success', 'Paciente internado.');
    }

    public function show(Hospitalization $hospitalization)
    {
        $hospitalization->load(['pet.client', 'pet.species', 'pet.breed', 'consultation', 'kennel', 'veterinarian', 'discharger',
            'treatments.doses.doneBy', 'treatments.product', 'records.recorder', 'charges', 'sale']);

        return view('admin.hospitalizations.show', [
            'h' => $hospitalization,
            'kennels' => $this->freeKennels($hospitalization->kennel_id),
            'products' => $this->inventoryProducts(),
            'latestVitals' => $hospitalization->records->first(fn ($r) => $r->temperature !== null || $r->heart_rate !== null || $r->respiratory_rate !== null || $r->weight !== null),
        ]);
    }

    public function moveKennel(Hospitalization $hospitalization)
    {
        $validated = request()->validate([
            'kennel_id' => ['nullable', Rule::exists('kennels', 'id')->where('company_id', app(Tenancy::class)->id())->where('active', true)],
        ]);

        Hospitalizations::moveToKennel($hospitalization, ! empty($validated['kennel_id']) ? (int) $validated['kennel_id'] : null);

        return back()->with('success', 'Ubicación actualizada.');
    }

    public function storeTreatment(Hospitalization $hospitalization)
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'medication' => 'required_without:product_id|nullable|string|max:255',
            'dose' => 'required|string|max:60',
            'stock_quantity' => 'nullable|numeric|min:0|max:99999',
            'route' => ['required', Rule::in(array_keys(PrescriptionItem::ROUTES))],
            'frequency_hours' => 'required|integer|min:1|max:168',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'instructions' => 'nullable|string|max:255',
        ], [
            'medication.required_without' => 'Indica el medicamento o elige un producto del inventario.',
            'ends_at.after_or_equal' => 'El fin debe ser posterior al inicio.',
        ], [
            'dose' => 'dosis',
            'route' => 'vía',
            'frequency_hours' => 'frecuencia',
            'starts_at' => 'inicio',
            'ends_at' => 'fin',
        ]);

        if (! empty($validated['product_id']) && empty($validated['medication'])) {
            $validated['medication'] = Product::find($validated['product_id'])->label;
        }

        Hospitalizations::addTreatment($hospitalization, array_merge($validated, [
            'starts_at' => Carbon::parse($validated['starts_at']),
            'ends_at' => Carbon::parse($validated['ends_at']),
        ]));

        return redirect()->route('hospitalizations.show', $hospitalization)->with('success', 'Tratamiento programado.');
    }

    public function stopTreatment(Hospitalization $hospitalization, HospitalizationTreatment $treatment)
    {
        abort_unless($treatment->hospitalization_id === $hospitalization->id, 404);

        Hospitalizations::stopTreatment($treatment);

        return back()->with('success', 'Tratamiento suspendido.');
    }

    public function giveDose(HospitalizationDose $dose)
    {
        $validated = request()->validate(['notes' => 'nullable|string|max:255']);

        Hospitalizations::giveDose($dose, $validated['notes'] ?? null);

        return back()->with('success', 'Toma registrada.');
    }

    public function skipDose(HospitalizationDose $dose)
    {
        $validated = request()->validate(['notes' => 'required|string|max:255'], ['notes.required' => 'Indica por qué se omite la toma.']);

        Hospitalizations::skipDose($dose, $validated['notes']);

        return back()->with('success', 'Toma omitida.');
    }

    /** Cuidados, signos vitales, evolución e incidencias. */
    public function storeRecord(Hospitalization $hospitalization)
    {
        $this->ensureActive($hospitalization);

        $validated = request()->validate([
            'type' => ['required', Rule::in(array_keys(HospitalizationRecord::TYPES))],
            'recorded_at' => 'required|date|before_or_equal:now',
            'notes' => 'nullable|string|max:5000',
            'temperature' => 'nullable|numeric|min:25|max:45',
            'heart_rate' => 'nullable|integer|min:1|max:400',
            'respiratory_rate' => 'nullable|integer|min:1|max:300',
            'weight' => 'nullable|numeric|min:0.01|max:9999',
            'hydration' => ['nullable', Rule::in(array_keys(Consultation::HYDRATION))],
            'condition' => ['nullable', Rule::in(array_keys(Hospitalization::CONDITIONS))],
        ], [], [
            'recorded_at' => 'fecha',
            'temperature' => 'temperatura',
            'heart_rate' => 'frecuencia cardíaca',
            'respiratory_rate' => 'frecuencia respiratoria',
            'weight' => 'peso',
        ]);

        $hasContent = collect($validated)->except(['type', 'recorded_at'])->filter(fn ($value) => filled($value))->isNotEmpty();
        if (! $hasContent) {
            throw ValidationException::withMessages(['notes' => 'Escribe una observación o registra algún valor.']);
        }

        DB::transaction(function () use ($hospitalization, $validated) {
            $hospitalization->records()->create($validated + ['recorded_by' => auth()->id()]);

            if (! empty($validated['condition'])) {
                $hospitalization->update(['condition' => $validated['condition']]);
            }

            // El peso medido pasa al historial de la mascota.
            if (! empty($validated['weight'])) {
                $pet = $hospitalization->pet;
                $pet->weights()->create([
                    'weight' => $validated['weight'],
                    'recorded_at' => Carbon::parse($validated['recorded_at'])->toDateString(),
                    'notes' => "Internación {$hospitalization->code}",
                    'recorded_by' => auth()->id(),
                ]);
                $pet->syncCurrentWeight();
            }
        });

        return redirect()->route('hospitalizations.show', ['hospitalization' => $hospitalization, 'tab' => 'records'])->with('success', 'Registro agregado.');
    }

    public function storeCharge(Hospitalization $hospitalization)
    {
        $this->ensureActive($hospitalization);

        $validated = request()->validate([
            'description' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.01|max:99999',
            'unit_price' => 'required|numeric|min:0|max:9999999',
        ], [], ['description' => 'concepto', 'quantity' => 'cantidad', 'unit_price' => 'precio']);

        $hospitalization->charges()->create($validated + [
            'total' => round((float) $validated['quantity'] * (float) $validated['unit_price'], 2),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hospitalizations.show', ['hospitalization' => $hospitalization, 'tab' => 'costs'])->with('success', 'Cargo agregado.');
    }

    public function destroyCharge(Hospitalization $hospitalization, HospitalizationCharge $charge)
    {
        abort_unless($charge->hospitalization_id === $hospitalization->id, 404);
        $this->ensureActive($hospitalization);

        // El cargo de una toma respalda una salida de stock: no se quita suelto.
        if ($charge->hospitalization_dose_id) {
            return back()->withErrors(['error' => 'Este cargo corresponde a una toma aplicada y no se puede quitar.']);
        }

        $charge->delete();

        return redirect()->route('hospitalizations.show', ['hospitalization' => $hospitalization, 'tab' => 'costs'])->with('success', 'Cargo quitado.');
    }

    /** Alta médica o fallecimiento. */
    public function discharge(Hospitalization $hospitalization)
    {
        $validated = request()->validate([
            'outcome' => ['required', Rule::in(['discharged', 'deceased'])],
            'discharged_at' => ['required', 'date', 'before_or_equal:now', 'after_or_equal:' . $hospitalization->admitted_at->format('Y-m-d H:i')],
            'final_diagnosis' => 'required|string|max:255',
            'home_treatment' => 'nullable|string|max:5000',
            'discharge_instructions' => 'nullable|string|max:5000',
            'summary' => 'nullable|string|max:5000',
        ], [
            'discharged_at.after_or_equal' => 'El alta no puede ser anterior al ingreso.',
        ], ['discharged_at' => 'fecha de alta', 'final_diagnosis' => 'diagnóstico final']);

        Hospitalizations::discharge($hospitalization, $validated['outcome'], collect($validated)->except('outcome')->all());

        return redirect()->route('hospitalizations.show', $hospitalization)
            ->with('success', $validated['outcome'] === 'deceased' ? 'Fallecimiento registrado.' : 'Alta registrada.');
    }

    public function cancel(Hospitalization $hospitalization)
    {
        $validated = request()->validate(['cancel_reason' => 'required|string|max:255'], [], ['cancel_reason' => 'motivo']);

        Hospitalizations::cancel($hospitalization, $validated['cancel_reason']);

        return redirect()->route('hospitalizations.show', $hospitalization)->with('success', 'Internación anulada.');
    }

    /** Resumen de hospitalización imprimible. */
    public function report(Hospitalization $hospitalization)
    {
        abort_unless(in_array($hospitalization->status, ['discharged', 'deceased'], true), 404);

        $hospitalization->load(['pet.client', 'pet.species', 'pet.breed', 'kennel', 'veterinarian', 'discharger',
            'treatments.doses', 'records.recorder', 'charges']);

        return view('admin.hospitalizations.report', ['h' => $hospitalization, 'company' => app(Tenancy::class)->company()]);
    }

    /** Espacios activos sin paciente (más el que ya ocupa esta internación). */
    protected function freeKennels(?int $currentId = null)
    {
        $occupied = Hospitalization::where('status', 'active')->whereNotNull('kennel_id')
            ->when($currentId, fn ($q) => $q->where('kennel_id', '!=', $currentId))->pluck('kennel_id');

        return Kennel::where('active', true)->whereNotIn('id', $occupied)->orderBy('name')->get();
    }

    protected function inventoryProducts()
    {
        return app(Tenancy::class)->company()?->planAllows('inventario')
            ? Product::where('active', true)->orderBy('name')->get(['id', 'name', 'presentation'])
            : collect();
    }

    protected function ensureActive(Hospitalization $hospitalization): void
    {
        if (! $hospitalization->isActive()) {
            throw ValidationException::withMessages(['error' => 'La internación está cerrada: ya no admite cambios.']);
        }
    }

    protected function ensureProfessional(?int $userId, string $field): void
    {
        if ($userId && ! Staff::belongsTo($userId, app(Tenancy::class)->company())) {
            throw ValidationException::withMessages([$field => 'El profesional no pertenece a esta veterinaria.']);
        }
    }
}
