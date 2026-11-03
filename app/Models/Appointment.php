<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cita. También representa a un paciente en la sala de espera: un "walk-in"
 * es una cita creada en el momento, directamente en estado `waiting`.
 */
class Appointment extends Model
{
    use HasFactory, BelongsToCompany;

    /** estado => [etiqueta, clase de color Bootstrap] */
    public const STATUSES = [
        'pending' => ['Pendiente', 'secondary'],
        'confirmed' => ['Confirmada', 'primary'],
        'waiting' => ['En espera', 'warning'],
        'in_progress' => ['En atención', 'info'],
        'attended' => ['Atendida', 'success'],
        'cancelled' => ['Cancelada', 'danger'],
        'no_show' => ['No asistió', 'dark'],
    ];

    /** Estados que ocupan la agenda del veterinario (cuentan para solapamientos). */
    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'waiting', 'in_progress'];

    /**
     * Transiciones permitidas: acción => [estados de origen, estado destino, etiqueta].
     * Cualquier otro cambio de estado se rechaza.
     */
    public const TRANSITIONS = [
        'confirm' => [['pending'], 'confirmed', 'Confirmar'],
        'check_in' => [['pending', 'confirmed'], 'waiting', 'Llegó: pasar a espera'],
        'start' => [['pending', 'confirmed', 'waiting'], 'in_progress', 'Iniciar atención'],
        'finish' => [['in_progress'], 'attended', 'Finalizar atención'],
        'no_show' => [['pending', 'confirmed'], 'no_show', 'No asistió'],
        'cancel' => [['pending', 'confirmed', 'waiting'], 'cancelled', 'Cancelar'],
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'pet_id', 'veterinarian_id', 'service_id',
        'starts_at', 'ends_at', 'reason', 'notes', 'status', 'is_walk_in',
        'confirmed_at', 'checked_in_at', 'started_at', 'finished_at', 'cancelled_at',
        'cancellation_reason', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_walk_in' => 'boolean',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinarian_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function getDurationMinutesAttribute(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /** Minutos que lleva (o llevó) en la sala de espera. */
    public function getWaitingMinutesAttribute(): ?int
    {
        if (! $this->checked_in_at) {
            return null;
        }

        return (int) $this->checked_in_at->diffInMinutes($this->started_at ?? now());
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function canTransition(string $action): bool
    {
        return isset(self::TRANSITIONS[$action])
            && in_array($this->status, self::TRANSITIONS[$action][0], true);
    }

    /** Acciones disponibles desde el estado actual. @return array<string, string> acción => etiqueta */
    public function availableTransitions(): array
    {
        return collect(self::TRANSITIONS)
            ->filter(fn ($t) => in_array($this->status, $t[0], true))
            ->map(fn ($t) => $t[2])
            ->all();
    }

    /**
     * Aplica una transición y sella la hora en que ocurrió.
     * Devuelve false si no está permitida desde el estado actual.
     */
    public function transition(string $action, ?string $reason = null): bool
    {
        if (! $this->canTransition($action)) {
            return false;
        }

        $now = now();
        $this->status = self::TRANSITIONS[$action][1];

        match ($action) {
            'confirm' => $this->confirmed_at = $now,
            'check_in' => $this->checked_in_at = $now,
            'start' => [$this->started_at = $now, $this->checked_in_at ??= $now],
            'finish' => $this->finished_at = $now,
            'cancel' => [$this->cancelled_at = $now, $this->cancellation_reason = $reason],
            default => null,
        };

        return $this->save();
    }

    /** Citas activas del veterinario que se cruzan con el intervalo dado. */
    public function scopeOverlapping(Builder $query, int $veterinarianId, CarbonInterface $start, CarbonInterface $end, ?int $exceptId = null): Builder
    {
        return $query->where('veterinarian_id', $veterinarianId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId));
    }

    public function scopeOnDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query->whereBetween('starts_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()]);
    }

    /** Mensaje de recordatorio listo para enviar por WhatsApp; null sin número. */
    public function whatsappReminderUrl(?string $companyName = null): ?string
    {
        $url = $this->client?->whatsapp_url;

        if (! $url) {
            return null;
        }

        $text = sprintf(
            'Hola %s, le recordamos la cita de %s%s el %s a las %s%s. Por favor responda SÍ para confirmar. ¡Gracias!',
            $this->client->first_name,
            $this->pet?->name,
            $this->service ? " ({$this->service->name})" : '',
            $this->starts_at->translatedFormat('l j \d\e F'),
            $this->starts_at->format('H:i'),
            $companyName ? " en {$companyName}" : ''
        );

        return $url . '?text=' . rawurlencode($text);
    }
}
