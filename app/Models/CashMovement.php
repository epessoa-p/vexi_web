<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Entrada o salida de dinero de un turno de caja. */
class CashMovement extends Model
{
    use BelongsToCompany;

    /** tipo => [etiqueta, color Bootstrap] */
    public const TYPES = [
        'sale' => ['Venta', 'success'],
        'receivable' => ['Cobro de deuda', 'success'],
        'income' => ['Ingreso', 'primary'],
        'expense' => ['Egreso / gasto', 'danger'],
        'withdrawal' => ['Retiro', 'warning'],
        'refund' => ['Devolución', 'secondary'],
    ];

    protected $fillable = [
        'company_id', 'cash_session_id', 'type', 'payment_method_id', 'amount', 'description',
        'source_type', 'source_id', 'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function getTypeColorAttribute(): string
    {
        return self::TYPES[$this->type][1] ?? 'secondary';
    }
}
