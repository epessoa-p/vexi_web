<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Desparasitación interna, externa o ambas (§5.4). */
class Deworming extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPES = [
        'internal' => 'Interna',
        'external' => 'Externa',
        'both' => 'Interna y externa',
    ];

    protected $fillable = [
        'company_id', 'pet_id', 'consultation_id', 'product_id', 'type', 'product', 'dose', 'weight',
        'applied_at', 'next_due_at', 'veterinarian_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'applied_at' => 'date',
        'next_due_at' => 'date',
        'weight' => 'decimal:2',
    ];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class)->withTrashed();
    }

    /** Producto del inventario descontado (la columna `product` guarda el nombre). */
    public function inventoryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'veterinarian_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Días sugeridos hasta la próxima aplicación según el tipo. */
    public static function defaultIntervalDays(string $type): int
    {
        $intervals = config('vexi.deworming');

        return match ($type) {
            'external' => $intervals['external'],
            'both' => min($intervals['internal'], $intervals['external']),
            default => $intervals['internal'],
        };
    }

    /**
     * Pendientes: la última de cada tipo por mascota. Una posterior del mismo
     * tipo —o una "ambas"— la deja cubierta.
     */
    public function scopePendingDue(Builder $query): Builder
    {
        return $query->whereNotNull('dewormings.next_due_at')
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('dewormings as later')
                ->whereColumn('later.pet_id', 'dewormings.pet_id')
                ->where(fn ($q) => $q->whereColumn('later.type', 'dewormings.type')->orWhere('later.type', 'both'))
                ->where(fn ($q) => $q->whereColumn('later.applied_at', '>', 'dewormings.applied_at')
                    ->orWhere(fn ($q) => $q->whereColumn('later.applied_at', 'dewormings.applied_at')
                        ->whereColumn('later.id', '>', 'dewormings.id'))))
            ->whereHas('pet', fn ($q) => $q->where('status', 'active'));
    }
}
