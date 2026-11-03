@extends('layouts.app')

@section('title', 'Ventas')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-receipt"></i> Ventas</h1>
            <p class="text-muted mb-0">{{ $totals['count'] }} ventas · {{ $money($totals['total']) }} netos · {{ $money($totals['balance']) }} por cobrar</p>
        </div>
        @if($can('sales.create'))
            <a href="{{ route('pos.create') }}" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Nueva venta</a>
        @endif
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Nº o cliente</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="V-000123 o nombre">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todas</option>
                    <option value="paid" @selected(request('status') === 'paid')>Pagadas</option>
                    <option value="pending" @selected(request('status') === 'pending')>Con saldo</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Anuladas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Vendedor</label>
                <select name="user_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($branches->count() > 1)
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sucursal</label>
                    <select name="branch_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-1">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-1">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-search"></i></button>
                @if(request()->hasAny(['q', 'status', 'user_id', 'branch_id', 'from', 'to']))
                    <a href="{{ route('sales.index') }}" class="btn btn-light border"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Nº</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th class="text-end">Total</th><th class="text-end">Saldo</th><th>Estado</th><th class="pe-3"></th></tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            @php [$label, $color] = $sale->payment_status_badge; @endphp
                            <tr class="{{ $sale->isCancelled() ? 'text-muted' : '' }}">
                                <td class="ps-3"><a href="{{ route('sales.show', $sale) }}" class="fw-semibold text-decoration-none">{{ $sale->code }}</a></td>
                                <td class="small text-nowrap">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $sale->client?->full_name ?? 'Consumidor final' }}@if($sale->pet)<div class="small text-muted">{{ $sale->pet->name }}</div>@endif</td>
                                <td class="small">{{ $sale->user?->name }}</td>
                                <td class="text-end {{ $sale->isCancelled() ? 'text-decoration-line-through' : '' }}">{{ $money($sale->total) }}</td>
                                <td class="text-end {{ $sale->balance > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $sale->balance > 0 ? $money($sale->balance) : '—' }}</td>
                                <td><span class="badge bg-{{ $color }}">{{ $label }}</span>@if((float) $sale->refunded > 0 && ! $sale->isCancelled())<span class="badge bg-light text-dark border">con devolución</span>@endif</td>
                                <td class="pe-3 text-end text-nowrap">
                                    <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Comprobante"><i class="bi bi-printer"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-5">No hay ventas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $sales->links() }}</div>
</div>
@endsection
