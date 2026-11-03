<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Support\Cash;
use App\Support\Sales;
use App\Support\Staff;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

/** Gestión de ventas (§9.2): listado, detalle, comprobante, anulación, devolución y abonos. */
class SaleController extends Controller
{
    public function index()
    {
        $query = Sale::with(['client', 'pet', 'user'])
            ->when(request('q'), function ($q, $term) {
                $number = (int) preg_replace('/\D/', '', $term);
                $q->where(fn ($w) => $w->when($number, fn ($n) => $n->where('number', $number))
                    ->orWhereHas('client', fn ($c) => $c->search($term)));
            })
            ->when(request('status') === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->when(request('status') === 'pending', fn ($q) => $q->receivable())
            ->when(request('status') === 'paid', fn ($q) => $q->where('status', 'completed')->where('balance', '<=', 0))
            ->when(request('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when(request('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when(request('from'), fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when(request('to'), fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()));

        $completed = (clone $query)->where('status', 'completed');

        return view('admin.sales.index', [
            'sales' => $query->latest('id')->paginate(25)->withQueryString(),
            'totals' => [
                'count' => (clone $completed)->count(),
                'total' => (float) (clone $completed)->sum('total') - (float) (clone $completed)->sum('refunded'),
                'balance' => (float) (clone $completed)->sum('balance'),
            ],
            'users' => Staff::professionals(app(Tenancy::class)->company()),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load(['items.product', 'items.service', 'payments.paymentMethod', 'payments.user', 'returns.items.saleItem',
            'returns.paymentMethod', 'returns.user', 'client', 'pet', 'user', 'branch', 'canceller']);

        return view('admin.sales.show', [
            'sale' => $sale,
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('sort')->get(),
            'sessions' => Cash::enabled() ? Cash::openSessions() : collect(),
            'cashEnabled' => Cash::enabled(),
        ]);
    }

    /** Comprobante imprimible tipo ticket. */
    public function receipt(Sale $sale)
    {
        $sale->load(['items', 'payments.paymentMethod', 'client', 'pet', 'user', 'branch']);

        return view('admin.sales.receipt', ['sale' => $sale, 'company' => app(Tenancy::class)->company()]);
    }

    /** Abono a una venta con saldo (§9.5). */
    public function addPayment(Sale $sale)
    {
        $validated = request()->validate([
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('company_id', app(Tenancy::class)->id())],
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'reference' => 'nullable|string|max:80',
            'cash_session_id' => ['nullable', 'integer'],
        ], [], ['amount' => 'monto']);

        $method = PaymentMethod::findOrFail($validated['payment_method_id']);
        if ($method->requires_reference && empty($validated['reference'])) {
            return back()->withErrors(['reference' => "El pago con {$method->name} necesita un número de referencia."]);
        }

        Sales::addPayment($sale, $method, (float) $validated['amount'], $validated['reference'] ?? null,
            Cash::sessionFor($validated['cash_session_id'] ?? null));

        return back()->with('success', 'Abono registrado.');
    }

    public function returnItems(Sale $sale)
    {
        $validated = request()->validate([
            'quantities' => 'required|array',
            'quantities.*' => 'nullable|numeric|min:0',
            'payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')->where('company_id', app(Tenancy::class)->id())],
            'cash_session_id' => ['nullable', 'integer'],
            'reason' => 'nullable|string|max:255',
        ]);

        Sales::returnItems(
            $sale,
            $validated['quantities'],
            ! empty($validated['payment_method_id']) ? PaymentMethod::find($validated['payment_method_id']) : null,
            $this->optionalSession($validated),
            $validated['reason'] ?? null,
        );

        return back()->with('success', 'Devolución registrada.');
    }

    public function cancel(Sale $sale)
    {
        $validated = request()->validate([
            'cancel_reason' => 'required|string|max:255',
            'payment_method_id' => ['nullable', Rule::exists('payment_methods', 'id')->where('company_id', app(Tenancy::class)->id())],
            'cash_session_id' => ['nullable', 'integer'],
        ], [], ['cancel_reason' => 'motivo de anulación']);

        Sales::cancel(
            $sale,
            $validated['cancel_reason'],
            ! empty($validated['payment_method_id']) ? PaymentMethod::find($validated['payment_method_id']) : null,
            $this->optionalSession($validated),
        );

        return back()->with('success', "Venta {$sale->code} anulada.");
    }

    /** Caja elegida para el reembolso (Sales exige una si hay dinero que devolver). */
    protected function optionalSession(array $validated)
    {
        return Cash::enabled() && ! empty($validated['cash_session_id']) ? Cash::sessionFor($validated['cash_session_id']) : null;
    }
}
