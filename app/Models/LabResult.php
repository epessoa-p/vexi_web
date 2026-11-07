<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Valor obtenido en un estudio, con la referencia vigente al informarlo. */
class LabResult extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'lab_request_id', 'name', 'value', 'unit', 'reference_min', 'reference_max', 'reference_text', 'sort',
    ];

    protected $casts = [
        'reference_min' => 'float',
        'reference_max' => 'float',
        'sort' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    /** 'low', 'high', 'normal' o null si el valor o la referencia no son numéricos. */
    public function getFlagAttribute(): ?string
    {
        $value = str_replace(',', '.', trim((string) $this->value));

        if (! is_numeric($value) || ($this->reference_min === null && $this->reference_max === null)) {
            return null;
        }

        return match (true) {
            $this->reference_min !== null && (float) $value < $this->reference_min => 'low',
            $this->reference_max !== null && (float) $value > $this->reference_max => 'high',
            default => 'normal',
        };
    }

    /** Referencia legible: "37 – 55", "≥ 5" o el texto. */
    public function getReferenceLabelAttribute(): string
    {
        $number = fn (float $n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');

        return match (true) {
            $this->reference_min !== null && $this->reference_max !== null => $number($this->reference_min) . ' – ' . $number($this->reference_max),
            $this->reference_min !== null => '≥ ' . $number($this->reference_min),
            $this->reference_max !== null => '≤ ' . $number($this->reference_max),
            default => (string) $this->reference_text,
        };
    }
}
