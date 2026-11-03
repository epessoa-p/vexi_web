<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro del historial de peso de una mascota. */
class PetWeight extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'pet_id', 'weight', 'recorded_at', 'notes', 'recorded_by'];

    protected $casts = [
        'weight' => 'decimal:2',
        'recorded_at' => 'date',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
