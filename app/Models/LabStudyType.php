<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tipo de estudio de laboratorio o imagen (§6.2), con sus parámetros. */
class LabStudyType extends Model
{
    use HasFactory, BelongsToCompany;

    public const CATEGORIES = [
        'hematology' => 'Hematología',
        'biochemistry' => 'Bioquímica',
        'urine' => 'Orina',
        'parasitology' => 'Parasitología',
        'imaging' => 'Imagen',
        'other' => 'Otros',
    ];

    protected $fillable = ['company_id', 'name', 'category', 'description', 'price', 'active'];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function parameters(): HasMany
    {
        return $this->hasMany(LabStudyParameter::class)->orderBy('sort');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LabRequest::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
