<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ventas (§9): alta, cobros posteriores, devoluciones y anulación. Cada
 * operación deja coherentes en una sola transacción la venta, el stock
 * (kardex) y la caja.
 */
class Sales
{
    /**
     * @param  array{client_id?: ?int, pet_id?: ?int, branch?: ?Branch, session?: ?CashSession, discount?: float,
     *               due_date?: ?string, notes?: ?string,
     *               items: array<int, array{type: string, product_id?: ?int, service_id?: ?int, consultation_id?: ?int,
     *                                       description: string, quantity: float, unit_price: float, discount?: float, affects_stock?: bool}>,
     *               payments: array<int, array{method: PaymentMethod, amount: float, reference?: ?string}>}  $data
     */
    public static function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $companyId = app(Tenancy::class)->id();
            $branch = $data['branch'] ?? Stock::defaultBranch();
            $session = $data['session'] ?? null;

            // Líneas: el descuento de cada una no puede superar su importe.
            $lines = collect($data['items'])->map(function (array $item) {
                $gross = round((float) $item['quantity'] * (float) $item['unit_price'], 2);
                $discount = min(round((float) ($item['discount'] ?? 0), 2), $gross);

                return $item + ['discount' => $discount, 'total' => round($gross - $discount, 2)];
            });

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos un ítem a la venta.']);
            }

            $subtotal = round($lines->sum('total'), 2);
            $discount = min(round((float) ($data['discount'] ?? 0), 2), $subtotal);
            $total = round($subtotal - $discount, 2);

            [$payments, $paid] = self::settlePayments($data['payments'] ?? [], $total);
            $balance = round($total - $paid, 2);

            if ($balance > 0 && empty($data['client_id'])) {
                throw ValidationException::withMessages(['payments' => 'Para dejar saldo pendiente (venta a crédito) elige un cliente.']);
            }

            // Numeración correlativa: se bloquea la empresa para no repetir número.
            Company::whereKey($companyId)->lockForUpdate()->first();
            $number = (int) Sale::forCompany($companyId)->max('number') + 1;

            $sale = Sale::create([
                'company_id' => $companyId,
                'branch_id' => $branch->id,
                'cash_session_id' => $session?->id,
                'number' => $number,
                'client_id' => $data['client_id'] ?? null,
                'pet_id' => $data['pet_id'] ?? null,
                'user_id' => auth()->id(),
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'balance' => $balance,
                'due_date' => $balance > 0 ? ($data['due_date'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $item = $sale->items()->create([
                    'company_id' => $sale->company_id,
                    'type' => $line['type'],
                    'product_id' => $line['product_id'] ?? null,
                    'service_id' => $line['service_id'] ?? null,
                    'consultation_id' => $line['consultation_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                    'affects_stock' => (bool) ($line['affects_stock'] ?? false),
                ]);

                // Lo que se entrega en mostrador sale del stock; no se vende lo que no hay.
                if ($item->affects_stock && $item->product_id) {
                    Stock::consume(Product::findOrFail($item->product_id), $branch, (float) $item->quantity, 'sale', [
                        'source' => $sale,
                        'notes' => "Venta {$sale->code}",
                    ]);
                }
            }

            // Una consulta se cobra una sola vez.
            $consultationIds = $lines->pluck('consultation_id')->filter()->unique();
            if ($consultationIds->isNotEmpty()) {
                $already = Consultation::whereIn('id', $consultationIds)->whereNotNull('sale_id')->lockForUpdate()->exists();
                if ($already) {
                    throw ValidationException::withMessages(['items' => 'Una de las consultas ya fue cobrada en otra venta.']);
                }
                Consultation::whereIn('id', $consultationIds)->update(['sale_id' => $sale->id]);
            }

            foreach ($payments as $payment) {
                self::registerPayment($sale, $payment['method'], $payment['amount'], $payment['reference'] ?? null, $session, 'sale');
            }

            return $sale;
        });
    }

    /** Abono a una venta con saldo pendiente (§9.5). */
    public static function addPayment(Sale $sale, PaymentMethod $method, float $amount, ?string $reference, ?CashSession $session): void
    {
        DB::transaction(function () use ($sale, $method, $amount, $reference, $session) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['amount' => 'La venta está anulada.']);
            }

            if ($amount <= 0 || $amount > (float) $sale->balance) {
                throw ValidationException::withMessages(['amount' => 'El abono debe ser mayor a cero y no superar el saldo de ' . Money::format($sale->balance, app(Tenancy::class)->company()) . '.']);
            }

            self::registerPayment($sale, $method, $amount, $reference, $session, 'installment');
            $sale->update([
                'paid' => round((float) $sale->paid + $amount, 2),
                'balance' => round((float) $sale->balance - $amount, 2),
            ]);
        });
    }

    /**
     * Devolución parcial: reingresa al stock lo devuelto y, del importe,
     * primero se descuenta lo que el cliente aún debía y el resto se le
     * reembolsa.
     *
     * @param  array<int, float>  $quantities  sale_item_id => cantidad devuelta
     */
    public static function returnItems(Sale $sale, array $quantities, ?PaymentMethod $refundMethod, ?CashSession $session, ?string $reason): SaleReturn
    {
        return DB::transaction(function () use ($sale, $quantities, $refundMethod, $session, $reason) {
            $sale = Sale::lockForUpdate()->with('items')->findOrFail($sale->id);

            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['items' => 'La venta está anulada.']);
            }

            $rows = collect($quantities)
                ->map(fn ($qty, $id) => ['item' => $sale->items->firstWhere('id', (int) $id), 'quantity' => round((float) $qty, 2)])
                ->filter(fn ($row) => $row['item'] && $row['quantity'] > 0);

            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Indica qué cantidad se devuelve.']);
            }

            foreach ($rows as $row) {
                if ($row['quantity'] > $row['item']->returnableQuantity()) {
                    throw ValidationException::withMessages(['items' => "No se pueden devolver más de {$row['item']->returnableQuantity()} de «{$row['item']->description}»."]);
                }
            }

            // Importe al precio efectivo cobrado (con el descuento global prorrateado).
            $factor = (float) $sale->subtotal > 0 ? (float) $sale->total / (float) $sale->subtotal : 1;
            $amount = round($rows->sum(fn ($row) => $row['quantity'] * $row['item']->netUnitPrice() * $factor), 2);

            $creditReduction = min($amount, (float) $sale->balance);
            $refund = round($amount - $creditReduction, 2);

            self::ensureRefundable($refund, $refundMethod, $session);

            $return = SaleReturn::create([
                'company_id' => $sale->company_id,
                'sale_id' => $sale->id,
                'cash_session_id' => $refund > 0 ? $session?->id : null,
                'payment_method_id' => $refund > 0 ? $refundMethod?->id : null,
                'amount' => $refund,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                $item = $row['item'];
                $return->items()->create([
                    'company_id' => $sale->company_id,
                    'sale_item_id' => $item->id,
                    'quantity' => $row['quantity'],
                    'amount' => round($row['quantity'] * $item->netUnitPrice() * $factor, 2),
                ]);
                $item->increment('returned_quantity', $row['quantity']);

                if ($item->affects_stock && $item->product) {
                    Stock::restoreFrom($sale, $item->product, $row['quantity'], "Devolución de la venta {$sale->code}");
                }
            }

            if ($refund > 0 && $session) {
                Cash::record($session, 'refund', $refundMethod, -$refund, "Devolución venta {$sale->code}", $return);
            }

            $sale->update([
                'balance' => round((float) $sale->balance - $creditReduction, 2),
                'refunded' => round((float) $sale->refunded + $refund, 2),
            ]);

            return $return;
        });
    }

    /**
     * Anulación total: todo lo que salió del stock vuelve, se reembolsa lo
     * cobrado y las consultas quedan otra vez pendientes de cobro.
     */
    public static function cancel(Sale $sale, string $reason, ?PaymentMethod $refundMethod, ?CashSession $session): void
    {
        DB::transaction(function () use ($sale, $reason, $refundMethod, $session) {
            $sale = Sale::lockForUpdate()->with('items.product')->findOrFail($sale->id);

            if ($sale->isCancelled()) {
                throw ValidationException::withMessages(['cancel_reason' => 'La venta ya está anulada.']);
            }

            $refund = round((float) $sale->paid - (float) $sale->refunded, 2);

            self::ensureRefundable($refund, $refundMethod, $session);

            foreach ($sale->items as $item) {
                if ($item->affects_stock && $item->product) {
                    Stock::restoreFrom($sale, $item->product, $item->returnableQuantity(), "Anulación de la venta {$sale->code}");
                }
            }

            if ($refund > 0 && $session) {
                Cash::record($session, 'refund', $refundMethod, -$refund, "Anulación venta {$sale->code}", $sale);
            }

            Consultation::where('sale_id', $sale->id)->update(['sale_id' => null]);

            $sale->update([
                'status' => 'cancelled',
                'refunded' => round((float) $sale->refunded + max($refund, 0), 2),
                'balance' => 0,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancel_reason' => $reason,
            ]);
        });
    }

    /**
     * Ajusta los pagos al total: si se entregó de más, el excedente es vuelto
     * y solo puede salir del efectivo.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float} pagos netos y total pagado
     */
    protected static function settlePayments(array $payments, float $total): array
    {
        $payments = collect($payments)->filter(fn ($p) => (float) $p['amount'] > 0)->values();
        $sum = round($payments->sum('amount'), 2);
        $change = round(max($sum - $total, 0), 2);

        if ($change > 0) {
            $cash = round($payments->filter(fn ($p) => $p['method']->isCash())->sum('amount'), 2);
            if ($change > $cash) {
                throw ValidationException::withMessages(['payments' => 'Los pagos superan el total y solo el efectivo puede dar vuelto.']);
            }

            // El vuelto se descuenta de los pagos en efectivo, del último al primero.
            $payments = $payments->reverse()->map(function ($p) use (&$change) {
                if ($change > 0 && $p['method']->isCash()) {
                    $take = min($change, (float) $p['amount']);
                    $p['amount'] = round((float) $p['amount'] - $take, 2);
                    $change = round($change - $take, 2);
                }

                return $p;
            })->reverse()->filter(fn ($p) => (float) $p['amount'] > 0)->values();
        }

        foreach ($payments as $payment) {
            if ($payment['method']->requires_reference && empty($payment['reference'])) {
                throw ValidationException::withMessages(['payments' => "El pago con {$payment['method']->name} necesita un número de referencia."]);
            }
        }

        return [$payments->all(), round(min($sum, $total), 2)];
    }

    /** Un reembolso necesita método y, si la veterinaria usa caja, una caja abierta. */
    protected static function ensureRefundable(float $refund, ?PaymentMethod $method, ?CashSession $session): void
    {
        if ($refund <= 0) {
            return;
        }

        if (! $method) {
            throw ValidationException::withMessages(['payment_method_id' => 'Elige cómo se devuelve el dinero.']);
        }

        if (Cash::enabled() && ! $session) {
            throw ValidationException::withMessages(['cash_session_id' => 'Elige la caja abierta de la que sale el reembolso.']);
        }
    }

    protected static function registerPayment(Sale $sale, PaymentMethod $method, float $amount, ?string $reference, ?CashSession $session, string $kind): void
    {
        $payment = $sale->payments()->create([
            'company_id' => $sale->company_id,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session?->id,
            'kind' => $kind,
            'amount' => $amount,
            'reference' => $reference,
            'user_id' => auth()->id(),
            'paid_at' => now(),
        ]);

        if ($session) {
            Cash::record($session, $kind === 'sale' ? 'sale' : 'receivable', $method, $amount,
                ($kind === 'sale' ? 'Venta ' : 'Abono venta ') . $sale->code, $payment);
        }
    }
}
