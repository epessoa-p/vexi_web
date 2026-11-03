<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Support\Staff;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sala de espera (§3.5): quién llegó, en qué orden, cuánto lleva esperando
 * y quién lo atiende. Trabaja sobre las citas de hoy.
 */
class WaitingRoomController extends Controller
{
    public function index()
    {
        $today = Appointment::with(['pet', 'client', 'service', 'veterinarian', 'consultation'])
            ->onDate(now())
            ->get();

        return view('admin.waiting-room.index', [
            'waiting' => $today->where('status', 'waiting')->sortBy('checked_in_at')->values(),
            'inProgress' => $today->where('status', 'in_progress')->sortBy('started_at')->values(),
            'upcoming' => $today->whereIn('status', ['pending', 'confirmed'])->sortBy('starts_at')->values(),
            'done' => $today->whereIn('status', ['attended', 'no_show', 'cancelled'])->sortByDesc('updated_at')->values(),
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            'services' => Service::where('active', true)->orderBy('name')->get(),
        ]);
    }

    /** Paciente sin cita que llega ahora: entra directo a la espera. */
    public function walkIn()
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'pet_id' => [
                'required',
                Rule::exists('pets', 'id')->where('company_id', $companyId)->where('client_id', request('client_id'))->whereNull('deleted_at'),
            ],
            'service_id' => ['nullable', Rule::exists('services', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'veterinarian_id' => ['nullable', 'integer'],
            'reason' => 'nullable|string|max:255',
        ], ['pet_id.exists' => 'La mascota no pertenece al propietario elegido.'], [
            'client_id' => 'propietario',
            'pet_id' => 'mascota',
        ]);

        $this->ensureProfessional($validated['veterinarian_id'] ?? null);

        $minutes = Service::find($validated['service_id'] ?? null)?->duration_minutes ?? config('vexi.agenda.default_duration');

        Appointment::create($validated + [
            'starts_at' => now(),
            'ends_at' => now()->addMinutes($minutes),
            'status' => 'waiting',
            'is_walk_in' => true,
            'checked_in_at' => now(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('waiting-room.index')->with('success', 'Paciente agregado a la sala de espera.');
    }

    /** Asignar (o cambiar) el profesional que atenderá. */
    public function assign(Appointment $appointment)
    {
        $validated = request()->validate(['veterinarian_id' => ['nullable', 'integer']]);
        $this->ensureProfessional($validated['veterinarian_id'] ?? null);

        $appointment->update(['veterinarian_id' => $validated['veterinarian_id'] ?? null]);

        return back()->with('success', 'Profesional asignado.');
    }

    protected function ensureProfessional(?int $userId): void
    {
        if ($userId && ! Staff::belongsTo($userId, app(Tenancy::class)->company())) {
            throw ValidationException::withMessages(['veterinarian_id' => 'El profesional no pertenece a esta veterinaria.']);
        }
    }
}
