<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Solicitud de un estudio. Sus resultados se editan hasta completarla; después
 * quedan bloqueados (trazabilidad clínica): para corregir, se anula con motivo
 * y se solicita de nuevo.
 */
class LabRequest extends Model
{
    use HasFactory, BelongsToCompany;

    /** estado => [etiqueta, color Bootstrap] */
    public const STATUSES = [
        'requested' => ['Solicitado', 'secondary'],
        'in_process' => ['En proceso', 'info'],
        'completed' => ['Completado', 'success'],
        'delivered' => ['Entregado', 'primary'],
        'cancelled' => ['Anulado', 'dark'],
    ];

    /** Acción => [estados de origen, estado destino]. `complete` pasa por saveResults. */
    public const TRANSITIONS = [
        'start' => [['requested'], 'in_process'],
        'complete' => [['requested', 'in_process'], 'completed'],
        'deliver' => [['completed'], 'delivered'],
        'cancel' => [['requested', 'in_process', 'completed'], 'cancelled'],
    ];

    protected $fillable = [
        'company_id', 'pet_id', 'consultation_id', 'sale_id', 'lab_study_type_id', 'requested_by', 'requested_at', 'status',
        'external_lab', 'notes', 'result_date', 'responsible_id', 'result_notes',
        'started_at', 'completed_at', 'delivered_at', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'result_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function studyType(): BelongsTo
    {
        return $this->belongsTo(LabStudyType::class, 'lab_study_type_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class)->orderBy('sort');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LabAttachment::class);
    }

    /** Número legible, estable porque sale del id. */
    public function getCodeAttribute(): string
    {
        return 'L-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    /** ¿Se pueden cargar o cambiar resultados y adjuntos? */
    public function isEditable(): bool
    {
        return in_array($this->status, ['requested', 'in_process'], true);
    }

    public function canTransition(string $action): bool
    {
        return in_array($this->status, self::TRANSITIONS[$action][0] ?? [], true);
    }

    /** Aplica una transición y sella la hora. Devuelve false si no está permitida. */
    public function transition(string $action, ?string $reason = null): bool
    {
        if (! $this->canTransition($action)) {
            return false;
        }

        $this->status = self::TRANSITIONS[$action][1];

        match ($action) {
            'start' => $this->started_at = now(),
            'complete' => [$this->started_at ??= now(), $this->completed_at = now()],
            'deliver' => $this->delivered_at = now(),
            'cancel' => [$this->cancelled_at = now(), $this->cancel_reason = $reason],
        };

        return $this->save();
    }
}
