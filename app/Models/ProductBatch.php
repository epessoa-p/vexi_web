<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Lote de un producto en una sucursal, con su vencimiento (§7.4). */
class ProductBatch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'product_id', 'branch_id', 'lot', 'manufactured_at', 'expires_at', 'quantity', 'initial_quantity',
    ];

    protected $casts = [
        'manufactured_at' => 'date',
        'expires_at' => 'date',
        'quantity' => 'decimal:2',
        'initial_quantity' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(now()->startOfDay());
    }

    /** Vence dentro de la ventana de alerta configurada. */
    public function isExpiringSoon(): bool
    {
        return $this->expires_at !== null && ! $this->isExpired()
            && $this->expires_at->lte(now()->addDays(config('vexi.expiry_alert_days'))->endOfDay());
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }
}
