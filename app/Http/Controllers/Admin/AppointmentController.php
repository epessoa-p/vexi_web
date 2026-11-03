<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Service;
use App\Support\Staff;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Agenda: calendario (día/semana/mes), alta, reprogramación y estados de
 * las citas. Las rutas exigen plan:agenda además del permiso.
 */
class AppointmentController extends Controller
{
    public function index()
    {
        return view('admin.appointments.index', $this->filtersData());
    }

    /** Feed JSON para el calendario (FullCalendar pide ?start=&end=). */
    public function events(): JsonResponse
    {
        $start = $this->wallTime(request('start')) ?? now()->startOfMonth();
        $end = $this->wallTime(request('end')) ?? now()->endOfMonth();
        $canEdit = auth()->user()->hasPermissionInCompany('appointments.edit', app(Tenancy::class)->company());

        $appointments = $this->filtered(Appointment::query())
            ->with(['pet', 'client', 'service', 'veterinarian'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when(! request()->boolean('show_cancelled'), fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show']))
            ->get();

        return response()->json($appointments->map(fn (Appointment $a) => [
            'id' => $a->id,
            'title' => $a->pet?->name . ($a->service ? " · {$a->service->name}" : ''),
            'start' => $a->starts_at->format('Y-m-d\TH:i:s'),
            'end' => $a->ends_at->format('Y-m-d\TH:i:s'),
            'url' => route('appointments.show', $a),
            'backgroundColor' => $a->service?->calendar_color ?? '#0d6efd',
            'borderColor' => $a->service?->calendar_color ?? '#0d6efd',
            // Solo se arrastran las que aún no empezaron.
            'editable' => $canEdit && in_array($a->status, ['pending', 'confirmed'], true),
            'classNames' => ["fc-status-{$a->status}"],
            'extendedProps' => [
                'client' => $a->client?->full_name,
                'veterinarian' => $a->veterinarian?->name,
                'status' => $a->status_label,
                'statusColor' => $a->status_color,
            ],
        ]));
    }

    public function create()
    {
        $pet = request('pet_id') ? Pet::with('client')->find(request('pet_id')) : null;
        $client = $pet?->client ?? (request('client_id') ? Client::find(request('client_id')) : null);

        $start = request('start') ? Carbon::parse(request('start')) : now()->addHour()->startOfHour();

        return view('admin.appointments.create', $this->formData() + [
            'appointment' => null,
            'selectedClient' => $client,
            'selectedPetId' => $pet?->id,
            'defaultStart' => $start,
        ]);
    }

    public function store()
    {
        $data = $this->validateAppointment();

        $appointment = Appointment::create($data + [
            'status' => request()->boolean('confirmed') ? 'confirmed' : 'pending',
            'confirmed_at' => request()->boolean('confirmed') ? now() : null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('appointments.show', $appointment)->with('success', 'Cita agendada.');
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['client', 'pet.species', 'pet.breed', 'service', 'veterinarian', 'branch', 'creator', 'consultation']);

        return view('admin.appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        if (! $appointment->isActive()) {
            return redirect()->route('appointments.show', $appointment)
                ->withErrors(['error' => 'Solo se pueden modificar citas que no se cerraron.']);
        }

        return view('admin.appointments.edit', $this->formData() + [
            'appointment' => $appointment,
            'selectedClient' => $appointment->client,
            'selectedPetId' => $appointment->pet_id,
            'defaultStart' => $appointment->starts_at,
        ]);
    }

    public function update(Appointment $appointment)
    {
        if (! $appointment->isActive()) {
            return redirect()->route('appointments.show', $appointment)
                ->withErrors(['error' => 'Solo se pueden modificar citas que no se cerraron.']);
        }

        $appointment->update($this->validateAppointment($appointment));

        return redirect()->route('appointments.show', $appointment)->with('success', 'Cita actualizada.');
    }

    /** Reprogramar arrastrando en el calendario (JSON). */
    public function move(Appointment $appointment): JsonResponse
    {
        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            return response()->json(['message' => 'Esta cita ya no se puede mover.'], 422);
        }

        $validated = request()->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
        ]);

        $start = $this->wallTime($validated['starts_at']);
        $end = $this->wallTime($validated['ends_at']);

        if ($conflict = $this->conflictFor($appointment->veterinarian_id, $start, $end, $appointment->id)) {
            return response()->json(['message' => $conflict], 422);
        }

        $appointment->update(['starts_at' => $start, 'ends_at' => $end]);

        return response()->json(['message' => 'Cita reprogramada.']);
    }

    /** Cambio de estado: confirmar, llegó, iniciar, finalizar, no asistió, cancelar. */
    public function transition(Appointment $appointment, string $action)
    {
        abort_unless(isset(Appointment::TRANSITIONS[$action]), 404);

        $reason = null;
        if ($action === 'cancel') {
            $reason = request()->validate(['cancellation_reason' => 'nullable|string|max:255'])['cancellation_reason'] ?? null;
        }

        // Al iniciar la atención sin veterinario asignado, la toma quien la inicia.
        if ($action === 'start' && ! $appointment->veterinarian_id) {
            $appointment->veterinarian_id = auth()->id();
        }

        if (! $appointment->transition($action, $reason)) {
            return back()->withErrors(['error' => "No se puede «{$this->actionLabel($action)}» una cita {$appointment->status_label}."]);
        }

        return back()->with('success', "Cita: {$appointment->status_label}.");
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Cita eliminada.');
    }

    /** @return array<string, mixed> */
    protected function validateAppointment(?Appointment $appointment = null): array
    {
        $companyId = app(Tenancy::class)->id();
        $company = app(Tenancy::class)->company();

        $validated = request()->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'pet_id' => [
                'required',
                Rule::exists('pets', 'id')->where('company_id', $companyId)->where('client_id', request('client_id'))->whereNull('deleted_at'),
            ],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'veterinarian_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:5|max:1440',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ], [
            'pet_id.exists' => 'La mascota no pertenece al propietario elegido.',
        ], [
            'client_id' => 'propietario',
            'pet_id' => 'mascota',
            'service_id' => 'servicio',
            'date' => 'fecha',
            'time' => 'hora',
            'duration_minutes' => 'duración',
        ]);

        if (! empty($validated['veterinarian_id']) && ! Staff::belongsTo((int) $validated['veterinarian_id'], $company)) {
            throw ValidationException::withMessages(['veterinarian_id' => 'El profesional no pertenece a esta veterinaria.']);
        }

        $start = Carbon::parse("{$validated['date']} {$validated['time']}");
        $end = $start->copy()->addMinutes((int) $validated['duration_minutes']);

        if (! empty($validated['veterinarian_id']) && $conflict = $this->conflictFor((int) $validated['veterinarian_id'], $start, $end, $appointment?->id)) {
            throw ValidationException::withMessages(['time' => $conflict]);
        }

        return collect($validated)->except(['date', 'time', 'duration_minutes'])->all() + [
            'starts_at' => $start,
            'ends_at' => $end,
        ];
    }

    /** Mensaje de choque de horario del veterinario, o null si está libre. */
    protected function conflictFor(?int $veterinarianId, Carbon $start, Carbon $end, ?int $exceptId = null): ?string
    {
        if (! $veterinarianId) {
            return null;
        }

        $other = Appointment::overlapping($veterinarianId, $start, $end, $exceptId)->with('pet')->first();

        return $other
            ? sprintf('El profesional ya tiene una cita de %s a %s (%s).', $other->starts_at->format('H:i'), $other->ends_at->format('H:i'), $other->pet?->name)
            : null;
    }

    protected function filtered($query)
    {
        return $query
            ->when(request('veterinarian_id'), fn ($q, $id) => $q->where('veterinarian_id', $id))
            ->when(request('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when(request('service_id'), fn ($q, $id) => $q->where('service_id', $id));
    }

    /** @return array<string, mixed> */
    protected function filtersData(): array
    {
        return [
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
            'services' => Service::where('active', true)->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return $this->filtersData();
    }

    protected function actionLabel(string $action): string
    {
        return mb_strtolower(Appointment::TRANSITIONS[$action][2] ?? $action);
    }

    /**
     * Hora "de pared" en la zona de la veterinaria. El calendario trabaja en
     * modo UTC para no aplicar la zona del navegador: "2026-10-31T10:00:00Z"
     * significa las 10:00 locales, así que se descarta el sufijo de zona.
     */
    protected function wallTime(?string $value): ?Carbon
    {
        return $value ? Carbon::parse(substr($value, 0, 19)) : null;
    }
}
