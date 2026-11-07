<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una toma programada: pendiente, aplicada, omitida o cancelada. */
class HospitalizationDose extends Model
{
    use BelongsToCompany;

    /** estado => [etiqueta, color Bootstrap] */
    public const STATUSES = [
        'pending' => ['Pendiente', 'secondary'],
        'given' => ['Aplicada', 'success'],
        'skipped' => ['Omitida', 'warning'],
        'cancelled' => ['Cancelada', 'dark'],
    ];

    protected $fillable = [
        'company_id', 'hospitalization_treatment_id', 'scheduled_at', 'status', 'done_at', 'done_by', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(HospitalizationTreatment::class, 'hospitalization_treatment_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->scheduled_at->isPast();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }
}
