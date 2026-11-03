<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Producto o medicamento. La existencia vive en ProductStock (por sucursal)
 * y solo cambia a través de App\Support\Stock, que deja el movimiento en el
 * kardex.
 */
class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const UNITS = [
        'unidad' => 'Unidad',
        'comprimido' => 'Comprimido',
        'capsula' => 'Cápsula',
        'frasco' => 'Frasco',
        'ampolla' => 'Ampolla',
        'pipeta' => 'Pipeta',
        'sobre' => 'Sobre',
        'caja' => 'Caja',
        'dosis' => 'Dosis',
        'ml' => 'Mililitro (ml)',
        'g' => 'Gramo (g)',
        'kg' => 'Kilogramo (kg)',
    ];

    protected $fillable = [
        'company_id', 'product_category_id', 'code', 'barcode', 'name', 'description', 'brand',
        'presentation', 'unit', 'purchase_price', 'sale_price', 'min_stock', 'track_batches', 'image', 'active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'track_batches' => 'boolean',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Nombre con presentación, como se muestra en listas y recetas. */
    public function getLabelAttribute(): string
    {
        return $this->presentation ? "{$this->name} — {$this->presentation}" : $this->name;
    }

    public function getUnitLabelAttribute(): string
    {
        return self::UNITS[$this->unit] ?? $this->unit;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    /** Existencia total (todas las sucursales) o de una sucursal. */
    public function stockIn(?int $branchId = null): float
    {
        $stocks = $this->relationLoaded('stocks') ? $this->stocks : $this->stocks()->get();

        return (float) ($branchId ? $stocks->where('branch_id', $branchId)->sum('quantity') : $stocks->sum('quantity'));
    }

    public function isLowStock(?int $branchId = null): bool
    {
        return (float) $this->min_stock > 0 && $this->stockIn($branchId) <= (float) $this->min_stock;
    }

    /** Busca por nombre, código interno, código de barras o marca. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('code', $term)
            ->orWhere('barcode', $term)
            ->orWhere('brand', 'like', "%{$term}%"));
    }

    /** Existencia total en o por debajo del mínimo (solo productos con mínimo definido). */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('min_stock', '>', 0)
            ->whereRaw('(select coalesce(sum(quantity), 0) from product_stocks where product_stocks.product_id = products.id) <= products.min_stock');
    }
}
