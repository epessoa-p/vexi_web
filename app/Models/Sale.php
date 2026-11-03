<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Venta. Se crea, anula, devuelve y cobra solo a través de App\Support\Sales,
 * que mantiene coherentes stock, caja y saldo pendiente.
 */
class Sale extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'cash_session_id', 'number', 'client_id', 'pet_id', 'user_id', 'status',
        'subtotal', 'discount', 'total', 'paid', 'refunded', 'balance', 'due_date', 'notes',
        'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'number' => 'integer',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'refunded' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('paid_at');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class)->latest('id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Número de comprobante: V-000123. */
    public function getCodeAttribute(): string
    {
        return 'V-' . str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getPaymentStatusAttribute(): string
    {
        return match (true) {
            $this->isCancelled() => 'cancelled',
            (float) $this->balance <= 0 => 'paid',
            (float) $this->paid > 0 => 'partial',
            default => 'pending',
        };
    }

    /** [etiqueta, color] del estado de cobro. */
    public function getPaymentStatusBadgeAttribute(): array
    {
        return match ($this->payment_status) {
            'cancelled' => ['Anulada', 'dark'],
            'paid' => ['Pagada', 'success'],
            'partial' => ['Pago parcial', 'warning'],
            default => ['Pendiente', 'danger'],
        };
    }

    public function isOverdue(): bool
    {
        return (float) $this->balance > 0 && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    /** Ventas vigentes con saldo pendiente (cuentas por cobrar). */
    public function scopeReceivable(Builder $query): Builder
    {
        return $query->where('status', 'completed')->where('balance', '>', 0);
    }
}
