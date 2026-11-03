<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalePayment;

/** Cuentas por cobrar (§9.5): clientes deudores e historial de cobros. */
class ReceivableController extends Controller
{
    public function index()
    {
        $sales = Sale::with('client')->receivable()
            ->when(request('overdue'), fn ($q) => $q->whereNotNull('due_date')->where('due_date', '<', now()->startOfDay()))
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('created_at')
            ->get();

        // Agrupado por cliente: cuánto debe y su deuda más antigua.
        $debtors = $sales->groupBy('client_id')->map(fn ($group) => [
            'client' => $group->first()->client,
            'sales' => $group,
            'balance' => round((float) $group->sum('balance'), 2),
            'overdue' => $group->contains(fn (Sale $s) => $s->isOverdue()),
            'oldest' => $group->min('created_at'),
        ])->sortByDesc('balance')->values();

        return view('admin.receivables.index', [
            'debtors' => $debtors,
            'total' => round((float) $sales->sum('balance'), 2),
            'collections' => SalePayment::with(['sale.client', 'paymentMethod', 'user'])
                ->where('kind', 'installment')->latest('paid_at')->limit(30)->get(),
        ]);
    }
}
