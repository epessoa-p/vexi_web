<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parámetro de un estudio, con su valor de referencia. */
class LabStudyParameter extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'lab_study_type_id', 'name', 'unit', 'reference_min', 'reference_max', 'reference_text', 'sort',
    ];

    protected $casts = [
        'reference_min' => 'float',
        'reference_max' => 'float',
        'sort' => 'integer',
    ];

    public function studyType(): BelongsTo
    {
        return $this->belongsTo(LabStudyType::class, 'lab_study_type_id');
    }
}
