<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Consulta veterinaria. Mientras está `in_progress` se edita libremente;
 * al finalizarla queda cerrada (trazabilidad clínica) y solo admite
 * seguimientos.
 */
class Consultation extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = [
        'in_progress' => ['En proceso', 'info'],
        'finished' => ['Finalizada', 'success'],
    ];

    public const HYDRATION = [
        'normal' => 'Normal',
        'mild' => 'Deshidratación leve (~5 %)',
        'moderate' => 'Deshidratación moderada (6–8 %)',
        'severe' => 'Deshidratación severa (>8 %)',
    ];

    public const MUCOUS_MEMBRANES = [
        'pink' => 'Rosadas',
        'pale' => 'Pálidas',
        'cyanotic' => 'Cianóticas',
        'icteric' => 'Ictéricas',
        'congested' => 'Congestivas',
        'other' => 'Otro',
    ];

    /** Escala de condición corporal WSAVA (1–9). */
    public const BODY_CONDITION = [
        1 => '1 · Emaciado', 2 => '2 · Muy delgado', 3 => '3 · Delgado',
        4 => '4 · Ideal (bajo)', 5 => '5 · Ideal', 6 => '6 · Sobrepeso leve',
        7 => '7 · Sobrepeso', 8 => '8 · Obeso', 9 => '9 · Obesidad severa',
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'pet_id', 'client_id', 'appointment_id', 'sale_id', 'veterinarian_id',
        'status', 'started_at', 'finished_at',
        'reason', 'anamnesis', 'physical_exam', 'temperature', 'weight', 'heart_rate', 'respiratory_rate',
        'hydration', 'body_condition', 'mucous_membranes', 'clinical_notes',
        'instructions', 'recommendations', 'next_review_at', 'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'next_review_at' => 'date',
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'body_condition' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** Venta con la que se cobró (una consulta se cobra una sola vez). */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinarian_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ConsultationDiagnosis::class)->orderByRaw("CASE type WHEN 'primary' THEN 0 WHEN 'secondary' THEN 1 ELSE 2 END")->orderBy('sort');
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('sort');
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(ConsultationProcedure::class)->orderBy('sort');
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(ConsultationSupply::class)->orderBy('sort');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(ConsultationFollowup::class)->orderByDesc('recorded_at');
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function getPrimaryDiagnosisAttribute(): ?ConsultationDiagnosis
    {
        return $this->diagnoses->firstWhere('type', 'primary');
    }

    /** Número legible de la receta, estable porque sale del id de la consulta. */
    public function getPrescriptionNumberAttribute(): string
    {
        return 'R-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
