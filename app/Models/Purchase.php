<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Compra a un proveedor. Se crea, recibe, paga y anula solo a través de
 * App\Support\Purchases, que mantiene coherentes stock, caja y saldo.
 */
class Purchase extends Model
{
    use BelongsToCompany;

    /** estado => [etiqueta, color Bootstrap] */
    public const STATUSES = [
        'ordered' => ['Pedido', 'secondary'],
        'partial' => ['Recibida en parte', 'warning'],
        'received' => ['Recibida', 'success'],
        'cancelled' => ['Anulada', 'dark'],
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'supplier_id', 'number', 'document_number', 'purchased_at', 'status',
        'subtotal', 'discount', 'tax_rate', 'tax', 'total', 'paid', 'balance', 'due_date', 'invoice_file',
        'notes', 'user_id', 'received_at', 'closing_notes', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'number' => 'integer',
        'purchased_at' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(PurchaseReception::class)->latest('received_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class)->orderBy('paid_at');
    }

    public function getCodeAttribute(): string
    {
        return 'C-' . str_pad((string) $this->number, 6, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function getInvoiceUrlAttribute(): ?string
    {
        return $this->invoice_file ? asset('storage/' . $this->invoice_file) : null;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** ¿Queda mercadería por recibir? */
    public function isPending(): bool
    {
        return in_array($this->status, ['ordered', 'partial'], true);
    }

    public function isOverdue(): bool
    {
        return (float) $this->balance > 0 && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    /** Compras vigentes con saldo por pagar. */
    public function scopePayable(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled')->where('balance', '>', 0);
    }
}
