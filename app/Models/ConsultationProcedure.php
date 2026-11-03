<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Procedimiento realizado durante una consulta. */
class ConsultationProcedure extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'consultation_id', 'name', 'description', 'materials', 'medications',
        'performed_by', 'cost', 'sort',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
