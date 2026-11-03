<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aplicación de una vacuna a una mascota (§5.2). */
class Vaccination extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'pet_id', 'vaccine_id', 'consultation_id', 'applied_at', 'dose_number',
        'lot', 'lot_expires_at', 'veterinarian_id', 'next_due_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'applied_at' => 'date',
        'lot_expires_at' => 'date',
        'next_due_at' => 'date',
        'dose_number' => 'integer',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class)->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinarian_id');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * Solo la última aplicación de cada vacuna por mascota marca la próxima
     * dosis: las anteriores ya quedaron cubiertas por la siguiente.
     */
    public function scopePendingDue(Builder $query): Builder
    {
        return $query->whereNotNull('vaccinations.next_due_at')
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('vaccinations as later')
                ->whereColumn('later.pet_id', 'vaccinations.pet_id')
                ->whereColumn('later.vaccine_id', 'vaccinations.vaccine_id')
                ->where(fn ($q) => $q->whereColumn('later.applied_at', '>', 'vaccinations.applied_at')
                    ->orWhere(fn ($q) => $q->whereColumn('later.applied_at', 'vaccinations.applied_at')
                        ->whereColumn('later.id', '>', 'vaccinations.id'))))
            ->whereHas('pet', fn ($q) => $q->where('status', 'active'));
    }
}
