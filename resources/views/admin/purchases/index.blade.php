@extends('layouts.app')

@section('title', 'Compras')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-bag"></i> Compras</h1>
            <p class="text-muted mb-0">{{ $money($totals['total']) }} comprados · {{ $money($totals['balance']) }} por pagar</p>
        </div>
        @if($can('purchases.create'))
            <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-bag-plus"></i> Nueva compra</a>
        @endif
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Nº, factura o proveedor</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Proveedor</label>
                <select name="supplier_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todas</option>
                    <option value="ordered" @selected(request('status') === 'ordered')>Pedidos sin recibir</option>
                    <option value="partial" @selected(request('status') === 'partial')>Recibidas en parte</option>
                    <option value="received" @selected(request('status') === 'received')>Recibidas</option>
                    <option value="payable" @selected(request('status') === 'payable')>Con saldo por pagar</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Anuladas</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-search"></i></button>
                @if(request()->hasAny(['q', 'supplier_id', 'status', 'from', 'to']))
                    <a href="{{ route('purchases.index') }}" class="btn btn-light border"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Nº</th><th>Fecha</th><th>Proveedor</th><th>Factura</th><th>Estado</th><th class="text-end">Total</th><th class="text-end pe-3">Saldo</th></tr></thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                            <tr class="{{ $purchase->isCancelled() ? 'text-muted' : '' }}">
                                <td class="ps-3"><a href="{{ route('purchases.show', $purchase) }}" class="fw-semibold text-decoration-none">{{ $purchase->code }}</a></td>
                                <td class="small">{{ $purchase->purchased_at->format('d/m/Y') }}</td>
                                <td>{{ $purchase->supplier?->name }}</td>
                                <td class="small">{{ $purchase->document_number ?: '—' }}</td>
                                <td><span class="badge bg-{{ $purchase->status_color }}">{{ $purchase->status_label }}</span></td>
                                <td class="text-end">{{ $money($purchase->total) }}</td>
                                <td class="text-end pe-3 {{ $purchase->balance > 0 ? ($purchase->isOverdue() ? 'text-danger fw-bold' : 'text-danger') : 'text-muted' }}">
                                    {{ $purchase->balance > 0 ? $money($purchase->balance) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">No hay compras.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $purchases->links() }}</div>
</div>
@endsection
