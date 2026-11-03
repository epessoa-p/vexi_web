<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Producto pedido en una compra, con lo recibido hasta ahora. */
class PurchaseItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'purchase_id', 'product_id', 'quantity', 'received_quantity', 'unit_cost', 'discount', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'received_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function pendingQuantity(): float
    {
        return max(round((float) $this->quantity - (float) $this->received_quantity, 2), 0);
    }

    /** Costo unitario real: con el descuento de la línea repartido. */
    public function netUnitCost(): float
    {
        return (float) $this->quantity > 0 ? round((float) $this->total / (float) $this->quantity, 4) : 0;
    }
}
