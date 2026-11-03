<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Vacuna del catálogo, con su protocolo recomendado (§5.1). */
class Vaccine extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'product_id', 'name', 'manufacturer', 'presentation', 'description',
        'doses_in_series', 'series_interval_days', 'booster_interval_days', 'min_age_weeks', 'active',
    ];

    protected $casts = [
        'doses_in_series' => 'integer',
        'series_interval_days' => 'integer',
        'booster_interval_days' => 'integer',
        'min_age_weeks' => 'integer',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function species(): BelongsToMany
    {
        return $this->belongsToMany(Species::class, 'species_vaccine');
    }

    /** Producto del inventario que se descuenta al aplicarla (opcional). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(Vaccination::class);
    }

    /**
     * Próxima dosis según el protocolo: dentro de la serie inicial, a los
     * `series_interval_days`; completada la serie, al refuerzo. Null si el
     * protocolo no prevé otra dosis.
     */
    public function nextDueDate(CarbonInterface $appliedAt, int $doseNumber): ?CarbonInterface
    {
        $days = $doseNumber < $this->doses_in_series
            ? $this->series_interval_days
            : $this->booster_interval_days;

        return $days ? $appliedAt->copy()->addDays($days) : null;
    }

    /** Resumen legible del protocolo: "3 dosis cada 21 días · refuerzo anual". */
    public function getProtocolLabelAttribute(): string
    {
        $parts = [];

        if ($this->doses_in_series > 1) {
            $parts[] = "{$this->doses_in_series} dosis" . ($this->series_interval_days ? " cada {$this->series_interval_days} días" : '');
        } else {
            $parts[] = 'Dosis única';
        }

        if ($this->booster_interval_days) {
            $parts[] = match (true) {
                $this->booster_interval_days === 365 => 'refuerzo anual',
                $this->booster_interval_days % 365 === 0 => 'refuerzo cada ' . intdiv($this->booster_interval_days, 365) . ' años',
                default => "refuerzo cada {$this->booster_interval_days} días",
            };
        }

        if ($this->min_age_weeks) {
            $parts[] = "desde {$this->min_age_weeks} semanas";
        }

        return implode(' · ', $parts);
    }

    /** Sin especies asignadas, la vacuna se ofrece para cualquiera. */
    public function appliesTo(?int $speciesId): bool
    {
        return $this->species->isEmpty() || $this->species->contains('id', $speciesId);
    }
}
