<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Raza de una especie. */
class Breed extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = ['company_id', 'species_id', 'name', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
