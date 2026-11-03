<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseReception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Compras (§8): alta, recepción (parcial o total), pagos al proveedor,
 * cierre con diferencias y anulación. Cada operación deja coherentes en una
 * transacción la compra, el stock (kardex) y la caja.
 */
class Purchases
{
    /**
     * @param  array{supplier_id: int, branch: Branch, purchased_at: string, document_number?: ?string, discount?: float,
     *               tax_rate?: float, due_date?: ?string, notes?: ?string, invoice_file?: ?string, receive_now?: bool,
     *               session?: ?CashSession,
     *               items: array<int, array{product_id: int, quantity: float, unit_cost: float, discount?: float,
     *                                       lot?: ?string, expires_at?: ?string, manufactured_at?: ?string}>,
     *               payments?: array<int, array{method: PaymentMethod, amount: float, reference?: ?string}>}  $data
     */
    public static function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $companyId = app(Tenancy::class)->id();

            $lines = collect($data['items'])->map(function (array $item) {
                $gross = round((float) $item['quantity'] * (float) $item['unit_cost'], 2);
                $discount = min(round((float) ($item['discount'] ?? 0), 2), $gross);

                return $item + ['discount' => $discount, 'total' => round($gross - $discount, 2)];
            });

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos un producto a la compra.']);
            }

            $totals = self::totals((float) $lines->sum('total'), (float) ($data['discount'] ?? 0), (float) ($data['tax_rate'] ?? 0));
            $payments = collect($data['payments'] ?? [])->filter(fn ($p) => (float) $p['amount'] > 0);

            if (round($payments->sum('amount'), 2) > $totals['total']) {
                throw ValidationException::withMessages(['payments' => 'Lo pagado no puede superar el total de la compra.']);
            }

            // Numeración correlativa: se bloquea la empresa para no repetir número.
            Company::whereKey($companyId)->lockForUpdate()->first();
            $number = (int) Purchase::forCompany($companyId)->max('number') + 1;

            $purchase = Purchase::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch']->id,
                'supplier_id' => $data['supplier_id'],
                'number' => $number,
                'document_number' => $data['document_number'] ?? null,
                'purchased_at' => $data['purchased_at'],
                'status' => 'ordered',
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'tax_rate' => $totals['tax_rate'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'paid' => 0,
                'balance' => $totals['total'],
                'due_date' => $data['due_date'] ?? null,
                'invoice_file' => $data['invoice_file'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $receive = [];
            foreach ($lines as $line) {
                $item = $purchase->items()->create([
                    'company_id' => $companyId,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                ]);

                $receive[] = [
                    'item_id' => $item->id,
                    'quantity' => (float) $line['quantity'],
                    'lot' => $line['lot'] ?? null,
                    'expires_at' => $line['expires_at'] ?? null,
                    'manufactured_at' => $line['manufactured_at'] ?? null,
                ];
            }

            // Mercadería que llega con la factura: se recibe en el mismo acto.
            if (! empty($data['receive_now'])) {
                self::receive($purchase, $receive, 'Recibida al registrar la compra');
            }

            foreach ($payments as $payment) {
                self::addPayment($purchase, $payment['method'], (float) $payment['amount'], $payment['reference'] ?? null, $data['session'] ?? null);
            }

            return $purchase->fresh();
        });
    }

    /**
     * Recepción: lo recibido entra al stock de la sucursal de la compra, con
     * su lote, al costo real de la línea (descuentos repartidos).
     *
     * @param  array<int, array{item_id: int, quantity: float, lot?: ?string, expires_at?: ?string, manufactured_at?: ?string}>  $lines
     */
    public static function receive(Purchase $purchase, array $lines, ?string $notes = null): PurchaseReception
    {
        return DB::transaction(function () use ($purchase, $lines, $notes) {
            $purchase = Purchase::lockForUpdate()->with(['items.product', 'branch', 'supplier'])->findOrFail($purchase->id);

            if (! $purchase->isPending()) {
                throw ValidationException::withMessages(['items' => 'Esta compra no tiene mercadería pendiente de recibir.']);
            }

            $rows = collect($lines)
                ->map(fn ($line) => $line + ['item' => $purchase->items->firstWhere('id', (int) $line['item_id'])])
                ->filter(fn ($line) => $line['item'] && (float) $line['quantity'] > 0);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Indica qué cantidad se recibió.']);
            }

            $reception = PurchaseReception::create([
                'company_id' => $purchase->company_id,
                'purchase_id' => $purchase->id,
                'received_at' => now(),
                'notes' => $notes,
                'user_id' => auth()->id(),
            ]);

            foreach ($rows as $line) {
                $item = $line['item'];
                $quantity = round((float) $line['quantity'], 2);

                if ($quantity > $item->pendingQuantity()) {
                    throw ValidationException::withMessages(['items' => "De «{$item->product->name}» quedan {$item->pendingQuantity()} por recibir."]);
                }

                Stock::receive($item->product, $purchase->branch, $quantity, 'purchase', [
                    'lot' => $line['lot'] ?? null,
                    'expires_at' => $line['expires_at'] ?? null,
                    'manufactured_at' => $line['manufactured_at'] ?? null,
                    'unit_cost' => $item->netUnitCost(),
                    'source' => $purchase,
                    'notes' => "Compra {$purchase->code} · {$purchase->supplier?->name}",
                ]);

                $reception->items()->create([
                    'company_id' => $purchase->company_id,
                    'purchase_item_id' => $item->id,
                    'quantity' => $quantity,
                    'lot' => $line['lot'] ?? null,
                    'expires_at' => $line['expires_at'] ?? null,
                    'manufactured_at' => $line['manufactured_at'] ?? null,
                ]);
                $item->increment('received_quantity', $quantity);
            }

            $complete = $purchase->items->every(fn ($item) => $item->fresh()->pendingQuantity() <= 0);
            $purchase->update([
                'status' => $complete ? 'received' : 'partial',
                'received_at' => $complete ? now() : $purchase->received_at,
            ]);

            return $reception;
        });
    }

    /**
     * Da por terminada una compra recibida en parte: lo que no llegó ya no se
     * espera y lo que se debe al proveedor se ajusta a lo recibido.
     */
    public static function closeWithDifferences(Purchase $purchase, string $notes): void
    {
        DB::transaction(function () use ($purchase, $notes) {
            $purchase = Purchase::lockForUpdate()->with('items')->findOrFail($purchase->id);

            if ($purchase->status !== 'partial') {
                throw ValidationException::withMessages(['closing_notes' => 'Solo se cierra con diferencias una compra recibida en parte.']);
            }

            // Mismo descuento global e impuesto, en proporción a lo recibido.
            $received = round($purchase->items->sum(fn ($item) => (float) $item->received_quantity * $item->netUnitCost()), 2);
            $ratio = (float) $purchase->subtotal > 0 ? $received / (float) $purchase->subtotal : 0;
            $totals = self::totals($received, round((float) $purchase->discount * $ratio, 2), (float) $purchase->tax_rate);

            $purchase->update($totals + [
                'status' => 'received',
                'received_at' => now(),
                'closing_notes' => $notes,
                'balance' => max(round($totals['total'] - (float) $purchase->paid, 2), 0),
            ]);
        });
    }

    /** Pago al proveedor (§8.4); si sale de una caja, queda como egreso. */
    public static function addPayment(Purchase $purchase, PaymentMethod $method, float $amount, ?string $reference, ?CashSession $session): void
    {
        DB::transaction(function () use ($purchase, $method, $amount, $reference, $session) {
            $purchase = Purchase::lockForUpdate()->with('supplier')->findOrFail($purchase->id);

            if ($purchase->isCancelled()) {
                throw ValidationException::withMessages(['amount' => 'La compra está anulada.']);
            }

            if ($amount <= 0 || round($amount, 2) > (float) $purchase->balance) {
                throw ValidationException::withMessages(['amount' => 'El pago debe ser mayor a cero y no superar el saldo de ' . Money::format($purchase->balance, app(Tenancy::class)->company()) . '.']);
            }

            if ($method->requires_reference && empty($reference)) {
                throw ValidationException::withMessages(['reference' => "El pago con {$method->name} necesita un número de referencia."]);
            }

            $payment = $purchase->payments()->create([
                'company_id' => $purchase->company_id,
                'payment_method_id' => $method->id,
                'cash_session_id' => $session?->id,
                'amount' => $amount,
                'reference' => $reference,
                'paid_at' => now(),
                'user_id' => auth()->id(),
            ]);

            if ($session) {
                Cash::record($session, 'expense', $method, -$amount, "Pago a proveedor {$purchase->code} · {$purchase->supplier?->name}", $payment);
            }

            $purchase->update([
                'paid' => round((float) $purchase->paid + $amount, 2),
                'balance' => round((float) $purchase->balance - $amount, 2),
            ]);
        });
    }

    /** Solo se anula una compra sin recepciones ni pagos: lo demás ya es historia. */
    public static function cancel(Purchase $purchase, string $reason): void
    {
        if ($purchase->receptions()->exists() || $purchase->payments()->exists()) {
            throw ValidationException::withMessages(['cancel_reason' => 'La compra ya tiene recepciones o pagos: no se puede anular.']);
        }

        $purchase->update(['status' => 'cancelled', 'balance' => 0, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
    }

    /** @return array{subtotal: float, discount: float, tax_rate: float, tax: float, total: float} */
    public static function totals(float $subtotal, float $discount, float $taxRate): array
    {
        $subtotal = round($subtotal, 2);
        $discount = min(round($discount, 2), $subtotal);
        $taxable = round($subtotal - $discount, 2);
        $tax = round($taxable * $taxRate / 100, 2);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_rate' => round($taxRate, 2),
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
        ];
    }
}
