<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Cash;
use App\Support\Purchases;
use App\Support\Stock;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/** Compras (§8.2–8.4): alta, recepción, pagos, cierre con diferencias y anulación. */
class PurchaseController extends Controller
{
    public function index()
    {
        $query = Purchase::with(['supplier', 'user'])
            ->when(request('q'), function ($q, $term) {
                $number = (int) preg_replace('/\D/', '', $term);
                $q->where(fn ($w) => $w->where('document_number', 'like', "%{$term}%")
                    ->when($number, fn ($n) => $n->orWhere('number', $number))
                    ->orWhereHas('supplier', fn ($s) => $s->search($term)));
            })
            ->when(request('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when(request('status'), fn ($q, $status) => $status === 'payable' ? $q->payable() : $q->where('status', $status))
            ->when(request('from'), fn ($q, $from) => $q->where('purchased_at', '>=', Carbon::parse($from)->toDateString()))
            ->when(request('to'), fn ($q, $to) => $q->where('purchased_at', '<=', Carbon::parse($to)->toDateString()));

        $valid = (clone $query)->where('status', '!=', 'cancelled');

        return view('admin.purchases.index', [
            'purchases' => $query->latest('purchased_at')->latest('id')->paginate(25)->withQueryString(),
            'totals' => ['total' => (float) (clone $valid)->sum('total'), 'balance' => (float) (clone $valid)->sum('balance')],
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('admin.purchases.create', [
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('sort')->get(),
            'sessions' => Cash::enabled() ? Cash::openSessions() : collect(),
        ]);
    }

    public function store()
    {
        $companyId = app(Tenancy::class)->id();
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        $validated = request()->validate([
            'supplier_id' => ['required', $exists('suppliers')->whereNull('deleted_at')],
            'branch_id' => ['nullable', $exists('branches')],
            'purchased_at' => 'required|date|before_or_equal:today',
            'document_number' => 'nullable|string|max:60',
            'discount' => 'nullable|numeric|min:0|max:9999999',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'due_date' => 'nullable|date|after_or_equal:purchased_at',
            'notes' => 'nullable|string|max:2000',
            'invoice_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'receive_now' => 'sometimes|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', $exists('products')->whereNull('deleted_at')],
            'items.*.quantity' => 'required|numeric|min:0.01|max:9999999',
            'items.*.unit_cost' => 'required|numeric|min:0|max:9999999',
            'items.*.discount' => 'nullable|numeric|min:0|max:9999999',
            'items.*.lot' => 'nullable|string|max:60',
            'items.*.expires_at' => 'nullable|date',
            'items.*.manufactured_at' => 'nullable|date|before_or_equal:today',
            'payment_method_id' => ['nullable', $exists('payment_methods')],
            'paid_amount' => 'nullable|numeric|min:0|max:9999999',
            'reference' => 'nullable|string|max:80',
            'cash_session_id' => ['nullable', 'integer'],
        ], [
            'items.required' => 'Agrega al menos un producto a la compra.',
        ], [
            'supplier_id' => 'proveedor',
            'purchased_at' => 'fecha de compra',
            'tax_rate' => 'impuesto',
            'invoice_file' => 'factura',
        ]);

        $paid = (float) ($validated['paid_amount'] ?? 0);
        $method = ! empty($validated['payment_method_id']) ? PaymentMethod::find($validated['payment_method_id']) : null;
        if ($paid > 0 && ! $method) {
            return back()->withInput()->withErrors(['payment_method_id' => 'Indica con qué se pagó.']);
        }

        $purchase = Purchases::create([
            'supplier_id' => (int) $validated['supplier_id'],
            'branch' => ! empty($validated['branch_id']) ? Branch::find($validated['branch_id']) : Stock::defaultBranch(),
            'purchased_at' => $validated['purchased_at'],
            'document_number' => $validated['document_number'] ?? null,
            'discount' => (float) ($validated['discount'] ?? 0),
            'tax_rate' => (float) ($validated['tax_rate'] ?? 0),
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'invoice_file' => request()->file('invoice_file')?->store('purchases/' . $companyId, 'public'),
            'receive_now' => request()->boolean('receive_now'),
            'items' => array_values($validated['items']),
            'payments' => $paid > 0 ? [['method' => $method, 'amount' => $paid, 'reference' => $validated['reference'] ?? null]] : [],
            'session' => $paid > 0 && ! empty($validated['cash_session_id']) ? Cash::sessionFor((int) $validated['cash_session_id']) : null,
        ]);

        return redirect()->route('purchases.show', $purchase)->with('success', "Compra {$purchase->code} registrada.");
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'branch', 'user', 'items.product', 'receptions.items.purchaseItem.product', 'receptions.user',
            'payments.paymentMethod', 'payments.user']);

        return view('admin.purchases.show', [
            'purchase' => $purchase,
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('sort')->get(),
            'sessions' => Cash::enabled() ? Cash::openSessions() : collect(),
        ]);
    }

    /** Recepción parcial o total de lo pendiente. */
    public function receive(Purchase $purchase)
    {
        $validated = request()->validate([
            'items' => 'required|array',
            'items.*.quantity' => 'nullable|numeric|min:0|max:9999999',
            'items.*.lot' => 'nullable|string|max:60',
            'items.*.expires_at' => 'nullable|date',
            'items.*.manufactured_at' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:255',
        ]);

        Purchases::receive($purchase, collect($validated['items'])
            ->map(fn ($line, $itemId) => $line + ['item_id' => (int) $itemId, 'quantity' => (float) ($line['quantity'] ?? 0)])
            ->values()->all(), $validated['notes'] ?? null);

        return back()->with('success', 'Recepción registrada: el stock ya está actualizado.');
    }

    public function addPayment(Purchase $purchase)
    {
        $validated = request()->validate([
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('company_id', app(Tenancy::class)->id())],
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'reference' => 'nullable|string|max:80',
            'cash_session_id' => ['nullable', 'integer'],
        ], [], ['amount' => 'monto']);

        Purchases::addPayment($purchase, PaymentMethod::find($validated['payment_method_id']), (float) $validated['amount'],
            $validated['reference'] ?? null,
            ! empty($validated['cash_session_id']) ? Cash::sessionFor((int) $validated['cash_session_id']) : null);

        return back()->with('success', 'Pago al proveedor registrado.');
    }

    public function close(Purchase $purchase)
    {
        $validated = request()->validate(['closing_notes' => 'required|string|max:255'], [], ['closing_notes' => 'motivo']);

        Purchases::closeWithDifferences($purchase, $validated['closing_notes']);

        return back()->with('success', 'Compra cerrada con diferencias: el saldo con el proveedor se ajustó a lo recibido.');
    }

    public function cancel(Purchase $purchase)
    {
        $validated = request()->validate(['cancel_reason' => 'required|string|max:255'], [], ['cancel_reason' => 'motivo']);

        Purchases::cancel($purchase, $validated['cancel_reason']);

        return back()->with('success', "Compra {$purchase->code} anulada.");
    }
}
