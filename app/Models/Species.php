<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Especie (perro, gato…). Catálogo editable por cada veterinaria. */
class Species extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'species';

    protected $fillable = ['company_id', 'name', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class)->orderBy('name');
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
