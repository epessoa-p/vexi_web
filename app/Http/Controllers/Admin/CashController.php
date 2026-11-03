<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Support\Cash;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;

/** Caja (§9.4): apertura, ingresos/egresos/retiros, arqueo, cierre e historial. */
class CashController extends Controller
{
    public function index()
    {
        $cajas = Caja::with('branch')->where('active', true)->orderBy('name')->get()
            ->each(fn (Caja $caja) => $caja->setRelation('current', $caja->openSession()));

        return view('admin.cash.index', [
            'cajas' => $cajas,
            'history' => CashSession::with(['caja', 'opener', 'closer'])->where('status', 'closed')
                ->latest('closed_at')->paginate(15),
        ]);
    }

    public function open(Caja $caja)
    {
        $validated = request()->validate([
            'opening_amount' => 'required|numeric|min:0|max:9999999',
            'notes' => 'nullable|string|max:500',
        ], [], ['opening_amount' => 'saldo inicial']);

        $session = Cash::open($caja, (float) $validated['opening_amount'], $validated['notes'] ?? null);

        return redirect()->route('cash.show', $session)->with('success', "Caja {$caja->name} abierta.");
    }

    public function show(CashSession $session)
    {
        $session->load(['caja.branch', 'opener', 'closer']);

        return view('admin.cash.show', [
            'session' => $session,
            'movements' => $session->movements()->with(['paymentMethod', 'user'])->get(),
            'totals' => $session->totalsByMethod(),
            'expected' => $session->isOpen() ? $session->expectedCash() : (float) $session->expected_cash,
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('sort')->get(),
        ]);
    }

    /** Ingreso, egreso o retiro manual. */
    public function movement(CashSession $session)
    {
        $validated = request()->validate([
            'type' => ['required', Rule::in(['income', 'expense', 'withdrawal'])],
            'payment_method_id' => ['required', Rule::exists('payment_methods', 'id')->where('company_id', app(Tenancy::class)->id())],
            'amount' => 'required|numeric|min:0.01|max:9999999',
            'description' => 'required|string|max:255',
        ], [], ['amount' => 'monto', 'description' => 'concepto']);

        $sign = $validated['type'] === 'income' ? 1 : -1;
        Cash::record($session, $validated['type'], PaymentMethod::find($validated['payment_method_id']),
            $sign * (float) $validated['amount'], $validated['description']);

        return back()->with('success', 'Movimiento registrado.');
    }

    /** Arqueo y cierre. */
    public function close(CashSession $session)
    {
        $validated = request()->validate([
            'counted_cash' => 'required|numeric|min:0|max:9999999',
            'notes' => 'nullable|string|max:500',
        ], [], ['counted_cash' => 'efectivo contado']);

        $session = Cash::close($session, (float) $validated['counted_cash'], $validated['notes'] ?? null);

        $message = match (true) {
            (float) $session->difference == 0 => 'Caja cerrada: el arqueo cuadra.',
            $session->difference > 0 => 'Caja cerrada con sobrante de ' . number_format((float) $session->difference, 2) . '.',
            default => 'Caja cerrada con faltante de ' . number_format(abs((float) $session->difference), 2) . '.',
        };

        return redirect()->route('cash.show', $session)->with('success', $message);
    }
}
