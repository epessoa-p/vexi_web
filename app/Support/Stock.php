<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto por el que cambia la existencia de un producto.
 *
 * Cada operación deja su movimiento en el kardex con el saldo resultante y
 * actualiza, en la misma transacción, el caché por sucursal (ProductStock) y
 * los lotes. Las salidas consumen primero el lote que vence antes (FEFO) y
 * nunca un lote vencido, salvo que se elija a propósito.
 */
class Stock
{
    /**
     * Sucursal donde se mueve el stock cuando la operación no indica otra.
     * Una veterinaria sin sucursales recibe "Sucursal principal" en el primer
     * movimiento, para que el inventario funcione desde el primer día.
     */
    public static function defaultBranch(?int $companyId = null): Branch
    {
        $companyId ??= app(Tenancy::class)->id();

        return Branch::forCompany($companyId)->where('active', true)->orderBy('id')->first()
            ?? Branch::forCompany($companyId)->create([
                'company_id' => $companyId,
                'name' => 'Sucursal principal',
                'active' => true,
            ]);
    }

    /**
     * Entrada de mercadería. Con lote, se suma al lote existente o se crea.
     *
     * @param  array{lot?: ?string, expires_at?: mixed, manufactured_at?: mixed, unit_cost?: ?float, notes?: ?string, source?: ?Model, type?: string}  $options
     */
    public static function receive(Product $product, Branch $branch, float $quantity, string $reason, array $options = []): StockMovement
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor a cero.']);
        }

        if ($product->track_batches && empty($options['lot'])) {
            throw ValidationException::withMessages(['lot' => "{$product->name} se controla por lote: indica lote y vencimiento."]);
        }

        return DB::transaction(function () use ($product, $branch, $quantity, $reason, $options) {
            $batch = null;

            if (! empty($options['lot'])) {
                $batch = ProductBatch::forCompany($product->company_id)
                    ->where('product_id', $product->id)->where('branch_id', $branch->id)->where('lot', $options['lot'])
                    ->lockForUpdate()->first()
                    ?? new ProductBatch([
                        'company_id' => $product->company_id,
                        'product_id' => $product->id,
                        'branch_id' => $branch->id,
                        'lot' => $options['lot'],
                        'quantity' => 0,
                        'initial_quantity' => 0,
                    ]);

                $batch->fill(array_filter([
                    'expires_at' => $options['expires_at'] ?? null,
                    'manufactured_at' => $options['manufactured_at'] ?? null,
                ]));
                $batch->quantity = (float) $batch->quantity + $quantity;
                $batch->initial_quantity = (float) $batch->initial_quantity + $quantity;
                $batch->save();
            }

            if ($reason === 'purchase' && ! empty($options['unit_cost'])) {
                $product->update(['purchase_price' => $options['unit_cost']]);
            }

            return self::record($product, $branch, $batch, $options['type'] ?? 'in', $reason, $quantity, $options);
        });
    }

    /**
     * Salida. Sin lote elegido, consume FEFO entre los lotes no vencidos.
     * Con `allow_negative` (consumos clínicos ya realizados) registra la
     * salida aunque falte stock: la existencia queda negativa y a la vista.
     *
     * @param  array{batch?: ?ProductBatch, notes?: ?string, source?: ?Model, allow_negative?: bool, type?: string}  $options
     * @return Collection<int, StockMovement>
     */
    public static function consume(Product $product, Branch $branch, float $quantity, string $reason, array $options = []): Collection
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor a cero.']);
        }

        return DB::transaction(function () use ($product, $branch, $quantity, $reason, $options) {
            $type = $options['type'] ?? 'out';
            $allowNegative = $options['allow_negative'] ?? false;
            $movements = collect();
            $remaining = $quantity;

            if ($product->track_batches) {
                $batches = isset($options['batch'])
                    ? collect([ProductBatch::forCompany($product->company_id)->lockForUpdate()->findOrFail($options['batch']->id)])
                    : ProductBatch::forCompany($product->company_id)
                        ->where('product_id', $product->id)->where('branch_id', $branch->id)->available()
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->startOfDay()))
                        ->orderByRaw('expires_at is null')->orderBy('expires_at')->orderBy('id')
                        ->lockForUpdate()->get();

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($remaining, (float) $batch->quantity);
                    if ($take <= 0) {
                        continue;
                    }

                    $batch->decrement('quantity', $take);
                    $movements->push(self::record($product, $branch, $batch, $type, $reason, -$take, $options));
                    $remaining = round($remaining - $take, 2);
                }
            } else {
                $available = (float) self::stockRow($product, $branch)->quantity;
                $take = $allowNegative ? $remaining : min($remaining, max($available, 0));

                if ($take > 0) {
                    $movements->push(self::record($product, $branch, null, $type, $reason, -$take, $options));
                    $remaining = round($remaining - $take, 2);
                }
            }

            if ($remaining > 0) {
                if (! $allowNegative) {
                    throw ValidationException::withMessages([
                        'quantity' => sprintf('Stock insuficiente de %s en %s: faltan %s %s.',
                            $product->name, $branch->name, self::number($remaining), $product->unit_label),
                    ]);
                }

                // Faltante de un producto con lotes: sale sin lote y deja la existencia en negativo.
                $movements->push(self::record($product, $branch, null, $type, $reason, -$remaining, $options));
            }

            return $movements;
        });
    }

    /** Ajuste por inventario físico: lleva la existencia (o el lote) a lo contado. */
    public static function adjust(Product $product, Branch $branch, float $counted, ?ProductBatch $batch = null, ?string $notes = null): ?StockMovement
    {
        if ($counted < 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad contada no puede ser negativa.']);
        }

        if ($product->track_batches && ! $batch) {
            throw ValidationException::withMessages(['product_batch_id' => 'Elige el lote que contaste.']);
        }

        return DB::transaction(function () use ($product, $branch, $counted, $batch, $notes) {
            if ($batch) {
                $batch = ProductBatch::forCompany($product->company_id)->lockForUpdate()->findOrFail($batch->id);
                $difference = round($counted - (float) $batch->quantity, 2);
                $batch->update(['quantity' => $counted]);
            } else {
                $difference = round($counted - (float) self::stockRow($product, $branch)->quantity, 2);
            }

            if ($difference == 0) {
                return null;
            }

            return self::record($product, $branch, $batch, 'adjustment', 'count', $difference, ['notes' => $notes]);
        });
    }

    /** Traslado entre sucursales, conservando lote y vencimiento. */
    public static function transfer(Product $product, Branch $from, Branch $to, float $quantity, ?ProductBatch $batch = null, ?string $notes = null): void
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_branch_id' => 'La sucursal de destino debe ser distinta a la de origen.']);
        }

        DB::transaction(function () use ($product, $from, $to, $quantity, $batch, $notes) {
            $outs = self::consume($product, $from, $quantity, 'transfer', [
                'batch' => $batch, 'type' => 'transfer_out', 'notes' => $notes ?? "Hacia {$to->name}",
            ]);

            foreach ($outs as $out) {
                self::receive($product, $to, abs((float) $out->quantity), 'transfer', [
                    'type' => 'transfer_in',
                    'lot' => $out->batch?->lot,
                    'expires_at' => $out->batch?->expires_at,
                    'manufactured_at' => $out->batch?->manufactured_at,
                    'notes' => $notes ?? "Desde {$from->name}",
                ]);
            }
        });
    }

    /**
     * Anula los movimientos que originó un registro (vacuna borrada, etc.)
     * con movimientos inversos. Idempotente: no anula dos veces.
     */
    public static function reverse(Model $source, ?string $notes = null): void
    {
        DB::transaction(function () use ($source, $notes) {
            $movements = StockMovement::forCompany($source->company_id)
                ->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
                ->get();

            if ($movements->contains('reason', 'reversal')) {
                return;
            }

            foreach ($movements as $movement) {
                $product = $movement->product;
                $branch = $movement->branch;
                $batch = $movement->product_batch_id
                    ? ProductBatch::forCompany($source->company_id)->lockForUpdate()->find($movement->product_batch_id)
                    : null;

                $batch?->increment('quantity', -(float) $movement->quantity);

                self::record($product, $branch, $batch, $movement->quantity < 0 ? 'in' : 'out', 'reversal',
                    -(float) $movement->quantity, ['source' => $source, 'notes' => $notes ?? "Anula movimiento #{$movement->id}"]);
            }
        });
    }

    /**
     * Devuelve al stock parte de lo que sacó un registro (devolución o
     * anulación de una venta), a los mismos lotes de los que salió y sin
     * devolver nunca más de lo que salió. Los movimientos de devolución
     * quedan con el mismo origen, para llevar la cuenta.
     */
    public static function restoreFrom(Model $source, Product $product, float $quantity, ?string $notes = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($source, $product, $quantity, $notes) {
            $movements = StockMovement::forCompany($source->company_id)
                ->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())
                ->where('product_id', $product->id)
                ->get();

            // Lo que todavía se puede devolver, por sucursal y lote.
            $pending = $movements->groupBy(fn ($m) => $m->branch_id . '|' . $m->product_batch_id)
                ->map(fn ($group) => [
                    'movement' => $group->first(),
                    'quantity' => -round((float) $group->sum('quantity'), 2),
                ])
                ->filter(fn ($row) => $row['quantity'] > 0)
                ->sortByDesc(fn ($row) => $row['movement']->id);

            $remaining = $quantity;
            foreach ($pending as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $give = min($remaining, $row['quantity']);
                $batch = $row['movement']->product_batch_id
                    ? ProductBatch::forCompany($source->company_id)->lockForUpdate()->find($row['movement']->product_batch_id)
                    : null;

                $batch?->increment('quantity', $give);
                self::record($product, $row['movement']->branch, $batch, 'in', 'sale_return', $give, ['source' => $source, 'notes' => $notes]);
                $remaining = round($remaining - $give, 2);
            }
        });
    }

    /** Fila de existencia de la sucursal, bloqueada para la transacción en curso. */
    protected static function stockRow(Product $product, Branch $branch): ProductStock
    {
        ProductStock::forCompany($product->company_id)->firstOrCreate(
            ['company_id' => $product->company_id, 'product_id' => $product->id, 'branch_id' => $branch->id],
            ['quantity' => 0]
        );

        return ProductStock::forCompany($product->company_id)
            ->where('product_id', $product->id)->where('branch_id', $branch->id)
            ->lockForUpdate()->first();
    }

    /** Aplica el movimiento a la existencia de la sucursal y lo deja en el kardex. */
    protected static function record(Product $product, Branch $branch, ?ProductBatch $batch, string $type, string $reason, float $quantity, array $options): StockMovement
    {
        $stock = self::stockRow($product, $branch);
        $stock->quantity = round((float) $stock->quantity + $quantity, 2);
        $stock->save();

        $source = $options['source'] ?? null;

        return StockMovement::create([
            'company_id' => $product->company_id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'product_batch_id' => $batch?->id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => $quantity,
            'balance_after' => $stock->quantity,
            'unit_cost' => $options['unit_cost'] ?? null,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'notes' => $options['notes'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }

    /** Cantidad legible sin decimales sobrantes ("12", "2.5"), con el formato de Money. */
    public static function number(float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    }
}
