<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mascota = paciente. Tiene su propio expediente clínico: los módulos
 * clínicos (consultas, vacunas, hospitalización…) cuelgan de pet_id.
 */
class Pet extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const SEXES = [
        'male' => 'Macho',
        'female' => 'Hembra',
        'unknown' => 'Sin determinar',
    ];

    public const SIZES = [
        'mini' => 'Mini',
        'small' => 'Pequeño',
        'medium' => 'Mediano',
        'large' => 'Grande',
        'giant' => 'Gigante',
    ];

    public const STATUSES = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'deceased' => 'Fallecido',
    ];

    protected $fillable = [
        'company_id',
        'client_id',
        'species_id',
        'breed_id',
        'name',
        'sex',
        'sterilized',
        'birth_date',
        'weight',
        'color',
        'size',
        'microchip',
        'identification',
        'photo',
        'status',
        'notes',
        'medical_history',
        'allergies',
        'chronic_conditions',
        'previous_surgeries',
        'usual_medications',
        'clinical_alerts',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'weight' => 'decimal:2',
        'sterilized' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class)->orderByDesc('applied_at')->orderByDesc('id');
    }

    public function dewormings(): HasMany
    {
        return $this->hasMany(Deworming::class)->orderByDesc('applied_at')->orderByDesc('id');
    }

    public function hospitalizations(): HasMany
    {
        return $this->hasMany(Hospitalization::class)->orderByDesc('admitted_at');
    }

    public function labRequests(): HasMany
    {
        return $this->hasMany(LabRequest::class)->orderByDesc('requested_at')->orderByDesc('id');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class)->orderByDesc('started_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderByDesc('starts_at');
    }

    public function weights(): HasMany
    {
        return $this->hasMany(PetWeight::class)->orderByDesc('recorded_at')->orderByDesc('id');
    }

    /** Edad legible: "2 años 3 meses", "5 meses", "12 días". */
    public function getAgeLabelAttribute(): ?string
    {
        if (! $this->birth_date) {
            return null;
        }

        $diff = $this->birth_date->diff(now());

        if ($diff->invert) {
            return null;
        }

        $plural = fn (int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);

        if ($diff->y > 0) {
            return $plural($diff->y, 'año', 'años') . ($diff->m ? ' ' . $plural($diff->m, 'mes', 'meses') : '');
        }

        if ($diff->m > 0) {
            return $plural($diff->m, 'mes', 'meses');
        }

        return $plural($diff->d, 'día', 'días');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }

    public function getSexLabelAttribute(): string
    {
        return self::SEXES[$this->sex] ?? '-';
    }

    public function getSizeLabelAttribute(): ?string
    {
        return self::SIZES[$this->size] ?? null;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** ¿Tiene algo que el veterinario debe ver antes de atender? */
    public function hasClinicalWarnings(): bool
    {
        return filled($this->clinical_alerts) || filled($this->allergies);
    }

    /**
     * Fija el peso actual a partir del registro más reciente del historial.
     * Así editar o borrar un registro antiguo nunca pisa el peso vigente.
     */
    public function syncCurrentWeight(): void
    {
        $this->forceFill(['weight' => $this->weights()->value('weight')])->save();
    }

    /** Busca por nombre, microchip, identificación o datos del propietario. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = "%{$term}%";
            $q->where('name', 'like', $like)
              ->orWhere('microchip', 'like', $like)
              ->orWhere('identification', 'like', $like)
              ->orWhereHas('client', fn (Builder $c) => $c->search($term));
        });
    }
}
