<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Servicio que ofrece la veterinaria: consulta, vacunación, baño… */
class Service extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    /** categoría => [etiqueta, color por defecto en el calendario] */
    public const CATEGORIES = [
        'consulta' => ['Consulta', '#0d6efd'],
        'vacunacion' => ['Vacunación', '#198754'],
        'desparasitacion' => ['Desparasitación', '#20c997'],
        'cirugia' => ['Cirugía', '#dc3545'],
        'procedimiento' => ['Procedimiento', '#fd7e14'],
        'laboratorio' => ['Laboratorio', '#6f42c1'],
        'estetica' => ['Peluquería y baño', '#d63384'],
        'guarderia' => ['Guardería', '#0dcaf0'],
        'hospedaje' => ['Hospedaje', '#6c757d'],
        'otro' => ['Otro', '#495057'],
    ];

    protected $fillable = [
        'company_id', 'name', 'category', 'price', 'duration_minutes', 'color', 'description', 'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_minutes' => 'integer',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category][0] ?? $this->category;
    }

    /** Color propio, o el de su categoría. */
    public function getCalendarColorAttribute(): string
    {
        return $this->color ?: (self::CATEGORIES[$this->category][1] ?? '#0d6efd');
    }
}
