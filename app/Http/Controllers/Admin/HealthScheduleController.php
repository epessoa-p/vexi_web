<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deworming;
use App\Models\Pet;
use App\Models\Vaccination;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

/**
 * Calendario sanitario (§5.3): vacunas y desparasitaciones atrasadas,
 * próximas e historial reciente, más el carnet imprimible de cada mascota.
 */
class HealthScheduleController extends Controller
{
    public function index()
    {
        $tab = in_array(request('tab'), ['overdue', 'upcoming', 'history'], true) ? request('tab') : 'overdue';
        $kind = in_array(request('kind'), ['vaccines', 'dewormings'], true) ? request('kind') : null;
        $days = max(1, min(180, (int) request('days', config('vexi.health_alert_days'))));

        $today = now()->startOfDay();
        $until = $today->copy()->addDays($days)->endOfDay();

        $items = match ($tab) {
            'overdue' => $this->due(fn ($q) => $q->where('next_due_at', '<', $today), $kind),
            'upcoming' => $this->due(fn ($q) => $q->whereBetween('next_due_at', [$today, $until]), $kind),
            'history' => $this->history($kind),
        };

        return view('admin.health.index', [
            'items' => $items,
            'tab' => $tab,
            'kind' => $kind,
            'days' => $days,
            'counts' => [
                'overdue' => $this->due(fn ($q) => $q->where('next_due_at', '<', $today), $kind)->count(),
                'upcoming' => $this->due(fn ($q) => $q->whereBetween('next_due_at', [$today, $until]), $kind)->count(),
            ],
        ]);
    }

    /** Carnet sanitario imprimible de la mascota. */
    public function card(Pet $pet)
    {
        $pet->load(['client', 'species', 'breed', 'vaccinations.vaccine', 'vaccinations.veterinarian', 'dewormings.veterinarian']);

        return view('admin.health.card', [
            'pet' => $pet,
            'company' => app(Tenancy::class)->company(),
        ]);
    }

    /**
     * Vacunas y desparasitaciones pendientes, unificadas para la vista.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function due(callable $window, ?string $kind): Collection
    {
        $vaccinations = $kind === 'dewormings' ? collect() : $window(Vaccination::pendingDue())
            ->with(['pet.client', 'pet.species', 'vaccine'])->get()
            ->map(fn (Vaccination $v) => $this->row('vaccine', $v, $v->vaccine?->name,
                $v->dose_number >= ($v->vaccine?->doses_in_series ?? 1) ? 'Refuerzo' : 'Dosis ' . ($v->dose_number + 1)));

        $dewormings = $kind === 'vaccines' ? collect() : $window(Deworming::pendingDue())
            ->with(['pet.client', 'pet.species'])->get()
            ->map(fn (Deworming $d) => $this->row('deworming', $d, "Desparasitación {$d->type_label}", $d->product));

        return $vaccinations->concat($dewormings)->sortBy('due')->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function history(?string $kind): Collection
    {
        $since = now()->subDays(60)->startOfDay();

        $vaccinations = $kind === 'dewormings' ? collect() : Vaccination::with(['pet.client', 'pet.species', 'vaccine', 'veterinarian'])
            ->where('applied_at', '>=', $since)->get()
            ->map(fn (Vaccination $v) => $this->row('vaccine', $v, $v->vaccine?->name, "Dosis {$v->dose_number}" . ($v->lot ? " · lote {$v->lot}" : '')));

        $dewormings = $kind === 'vaccines' ? collect() : Deworming::with(['pet.client', 'pet.species', 'veterinarian'])
            ->where('applied_at', '>=', $since)->get()
            ->map(fn (Deworming $d) => $this->row('deworming', $d, "Desparasitación {$d->type_label}", $d->product));

        return $vaccinations->concat($dewormings)->sortByDesc('applied')->values();
    }

    /** @return array<string, mixed> */
    protected function row(string $type, Vaccination|Deworming $record, ?string $title, ?string $detail): array
    {
        return [
            'type' => $type,
            'record' => $record,
            'pet' => $record->pet,
            'title' => $title,
            'detail' => $detail,
            'applied' => $record->applied_at,
            'due' => $record->next_due_at,
            'veterinarian' => $record->veterinarian?->name,
        ];
    }
}
