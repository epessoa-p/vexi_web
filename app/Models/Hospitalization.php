<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Internación de un paciente. Se admite, trata y da de alta a través de
 * App\Support\Hospitalizations; cerrada, ya no admite cambios.
 */
class Hospitalization extends Model
{
    use HasFactory, BelongsToCompany;

    /** estado => [etiqueta, color Bootstrap] */
    public const STATUSES = [
        'active' => ['Internado', 'info'],
        'discharged' => ['Alta', 'success'],
        'deceased' => ['Fallecido', 'dark'],
        'cancelled' => ['Anulada', 'secondary'],
    ];

    /** Estado clínico del paciente: estado => [etiqueta, color Bootstrap] */
    public const CONDITIONS = [
        'stable' => ['Estable', 'success'],
        'improving' => ['Mejorando', 'primary'],
        'observation' => ['En observación', 'warning'],
        'critical' => ['Crítico', 'danger'],
    ];

    protected $fillable = [
        'company_id', 'pet_id', 'consultation_id', 'sale_id', 'kennel_id', 'veterinarian_id', 'status', 'condition',
        'admitted_at', 'reason', 'admission_diagnosis', 'notes', 'daily_rate',
        'discharged_at', 'final_diagnosis', 'home_treatment', 'discharge_instructions', 'summary',
        'discharged_by', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
        'daily_rate' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function kennel(): BelongsTo
    {
        return $this->belongsTo(Kennel::class);
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinarian_id');
    }

    public function discharger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(HospitalizationTreatment::class)->orderBy('starts_at');
    }

    public function doses(): HasManyThrough
    {
        return $this->hasManyThrough(HospitalizationDose::class, HospitalizationTreatment::class)->orderBy('scheduled_at');
    }

    public function records(): HasMany
    {
        return $this->hasMany(HospitalizationRecord::class)->orderByDesc('recorded_at')->orderByDesc('id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(HospitalizationCharge::class)->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getCodeAttribute(): string
    {
        return 'H-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function getConditionLabelAttribute(): string
    {
        return self::CONDITIONS[$this->condition][0] ?? $this->condition;
    }

    public function getConditionColorAttribute(): string
    {
        return self::CONDITIONS[$this->condition][1] ?? 'secondary';
    }

    /** Días facturables: cada 24 h o fracción, con un mínimo de uno. */
    public function stayDays(): int
    {
        $hours = $this->admitted_at->diffInHours($this->discharged_at ?? now());

        return max(1, (int) ceil($hours / 24));
    }

    public function stayCost(): float
    {
        return round($this->stayDays() * (float) $this->daily_rate, 2);
    }

    public function totalCost(): float
    {
        return round($this->stayCost() + (float) $this->charges()->sum('total'), 2);
    }
}
