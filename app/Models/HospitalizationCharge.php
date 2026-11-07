<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Costo asociado a la internación, manual o generado por una toma con producto. */
class HospitalizationCharge extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'hospitalization_id', 'hospitalization_dose_id', 'description', 'quantity', 'unit_price', 'total', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function dose(): BelongsTo
    {
        return $this->belongsTo(HospitalizationDose::class, 'hospitalization_dose_id');
    }

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }
}
