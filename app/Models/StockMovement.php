<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Movimiento del kardex. Inmutable: se crea solo desde App\Support\Stock. */
class StockMovement extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'in' => ['Entrada', 'success'],
        'out' => ['Salida', 'danger'],
        'adjustment' => ['Ajuste', 'warning'],
        'transfer_in' => ['Transferencia recibida', 'info'],
        'transfer_out' => ['Transferencia enviada', 'secondary'],
    ];

    public const REASONS = [
        'initial' => 'Stock inicial',
        'purchase' => 'Compra',
        'return' => 'Devolución',
        'donation' => 'Donación / muestra',
        'internal_use' => 'Consumo interno',
        'loss' => 'Merma / rotura',
        'expired' => 'Vencido',
        'count' => 'Inventario físico',
        'transfer' => 'Transferencia',
        'prescription' => 'Dispensado en receta',
        'procedure' => 'Insumo de consulta',
        'vaccination' => 'Vacunación',
        'deworming' => 'Desparasitación',
        'reversal' => 'Anulación',
        'sale' => 'Venta',
        'sale_return' => 'Devolución de venta',
        'other' => 'Otro',
    ];

    /** Motivos que se pueden elegir a mano en entradas y salidas. */
    public const MANUAL_IN_REASONS = ['purchase', 'initial', 'return', 'donation', 'other'];

    public const MANUAL_OUT_REASONS = ['internal_use', 'loss', 'expired', 'return', 'other'];

    protected $fillable = [
        'company_id', 'product_id', 'branch_id', 'product_batch_id', 'type', 'reason', 'quantity',
        'balance_after', 'unit_cost', 'source_type', 'source_id', 'notes', 'user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
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

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
