<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchasePayment;

/** Cuentas por pagar (§8.4): deuda con cada proveedor e historial de pagos. */
class PayableController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with('supplier')->payable()
            ->when(request('overdue'), fn ($q) => $q->whereNotNull('due_date')->where('due_date', '<', now()->startOfDay()))
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('purchased_at')
            ->get();

        $suppliers = $purchases->groupBy('supplier_id')->map(fn ($group) => [
            'supplier' => $group->first()->supplier,
            'purchases' => $group,
            'balance' => round((float) $group->sum('balance'), 2),
            'overdue' => $group->contains(fn (Purchase $p) => $p->isOverdue()),
        ])->sortByDesc('balance')->values();

        return view('admin.payables.index', [
            'suppliers' => $suppliers,
            'total' => round((float) $purchases->sum('balance'), 2),
            'payments' => PurchasePayment::with(['purchase.supplier', 'paymentMethod', 'user'])->latest('paid_at')->limit(30)->get(),
        ]);
    }
}
