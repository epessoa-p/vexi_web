<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Medicamento indicado en la receta de una consulta. */
class PrescriptionItem extends Model
{
    use BelongsToCompany;

    public const ROUTES = [
        'oral' => 'Oral',
        'im' => 'Intramuscular (IM)',
        'iv' => 'Intravenosa (IV)',
        'sc' => 'Subcutánea (SC)',
        'topical' => 'Tópica',
        'ophthalmic' => 'Oftálmica',
        'otic' => 'Ótica',
        'intranasal' => 'Intranasal',
        'rectal' => 'Rectal',
        'other' => 'Otra',
    ];

    protected $fillable = [
        'company_id', 'consultation_id', 'product_id', 'medication', 'presentation', 'dose', 'route',
        'frequency', 'duration', 'quantity', 'instructions', 'sort', 'dispensed_quantity', 'dispensed_at',
    ];

    protected $casts = [
        'dispensed_quantity' => 'decimal:2',
        'dispensed_at' => 'datetime',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getRouteLabelAttribute(): string
    {
        return self::ROUTES[$this->route] ?? $this->route;
    }
}
