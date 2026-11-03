<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evolución del paciente registrada después de la consulta. */
class ConsultationFollowup extends Model
{
    use BelongsToCompany;

    /** estado => [etiqueta, color Bootstrap] */
    public const TREATMENT_STATUSES = [
        'in_course' => ['En curso', 'secondary'],
        'improving' => ['Mejorando', 'success'],
        'unchanged' => ['Sin cambios', 'warning'],
        'worsening' => ['Empeorando', 'danger'],
        'resolved' => ['Resuelto', 'primary'],
    ];

    protected $fillable = ['company_id', 'consultation_id', 'recorded_at', 'treatment_status', 'notes', 'recorded_by'];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::TREATMENT_STATUSES[$this->treatment_status][0] ?? $this->treatment_status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::TREATMENT_STATUSES[$this->treatment_status][1] ?? 'secondary';
    }
}
