<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Diagnóstico de una consulta: principal, secundario o diferencial. */
class ConsultationDiagnosis extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'primary' => 'Principal',
        'secondary' => 'Secundario',
        'differential' => 'Diferencial',
    ];

    protected $table = 'consultation_diagnoses';

    protected $fillable = ['company_id', 'consultation_id', 'type', 'code', 'description', 'notes', 'sort'];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
