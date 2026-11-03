<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/** Turno de una caja: apertura, movimientos y cierre con arqueo (§9.4). */
class CashSession extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'caja_id', 'status', 'opened_by', 'opened_at', 'opening_amount',
        'closed_by', 'closed_at', 'expected_cash', 'counted_cash', 'difference', 'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class)->withTrashed();
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->latest('id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Efectivo que debería haber: saldo inicial + movimientos en efectivo. */
    public function expectedCash(): float
    {
        $cash = $this->movements()
            ->whereHas('paymentMethod', fn ($q) => $q->where('type', 'cash'))
            ->sum('amount');

        return round((float) $this->opening_amount + (float) $cash, 2);
    }

    /**
     * Totales del turno por método de pago (entradas y salidas netas).
     *
     * @return Collection<int, array{method: string, type: ?string, amount: float}>
     */
    public function totalsByMethod(): Collection
    {
        return $this->movements()->with('paymentMethod')->get()
            ->groupBy('payment_method_id')
            ->map(fn ($items) => [
                'method' => $items->first()->paymentMethod?->name ?? 'Sin método',
                'type' => $items->first()->paymentMethod?->type,
                'amount' => round((float) $items->sum('amount'), 2),
            ])
            ->values();
    }
}
