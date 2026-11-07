<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anotación de la internación: signos vitales, cuidados, evolución o incidencia. */
class HospitalizationRecord extends Model
{
    use BelongsToCompany;

    /** tipo => [etiqueta, ícono, color Bootstrap] */
    public const TYPES = [
        'evolution' => ['Evolución', 'bi-graph-up-arrow', 'primary'],
        'vitals' => ['Signos vitales', 'bi-heart-pulse', 'danger'],
        'feeding' => ['Alimentación', 'bi-egg-fried', 'success'],
        'hydration' => ['Hidratación', 'bi-droplet', 'info'],
        'care' => ['Cuidados', 'bi-bandaid', 'secondary'],
        'incident' => ['Incidencia', 'bi-exclamation-triangle', 'warning'],
    ];

    protected $fillable = [
        'company_id', 'hospitalization_id', 'type', 'recorded_at', 'notes', 'temperature', 'heart_rate',
        'respiratory_rate', 'weight', 'hydration', 'condition', 'recorded_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
    ];

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
