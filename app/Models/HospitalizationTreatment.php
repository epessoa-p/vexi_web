<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Medicamento programado durante la internación; genera su horario de tomas. */
class HospitalizationTreatment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'hospitalization_id', 'product_id', 'medication', 'dose', 'stock_quantity', 'route',
        'frequency_hours', 'starts_at', 'ends_at', 'instructions', 'stopped_at', 'created_by',
    ];

    protected $casts = [
        'stock_quantity' => 'decimal:2',
        'frequency_hours' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function doses(): HasMany
    {
        return $this->hasMany(HospitalizationDose::class)->orderBy('scheduled_at');
    }

    public function getRouteLabelAttribute(): string
    {
        return PrescriptionItem::ROUTES[$this->route] ?? $this->route;
    }
}
