<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** Método de pago (§9.3). Solo el tipo `cash` cuenta para el arqueo. */
class PaymentMethod extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia bancaria',
        'qr' => 'QR',
        'card' => 'Tarjeta',
        'other' => 'Otro',
    ];

    protected $fillable = ['company_id', 'name', 'type', 'requires_reference', 'active', 'sort'];

    protected $casts = [
        'requires_reference' => 'boolean',
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function isCash(): bool
    {
        return $this->type === 'cash';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
