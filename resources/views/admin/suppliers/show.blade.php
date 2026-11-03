@extends('layouts.app')

@section('title', $supplier->name)

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $supplier->name }} @unless($supplier->active)<span class="badge bg-secondary fs-6 align-middle">Inactivo</span>@endunless</h1>
            <div class="text-muted">
                {{ collect([$supplier->tax_id ? "NIT {$supplier->tax_id}" : null, $supplier->contact_name, $supplier->phone, $supplier->email])->filter()->implode(' · ') }}
            </div>
            @if($supplier->address)<div class="small text-muted">{{ $supplier->address }}</div>@endif
        </div>
        <div class="d-flex gap-2">
            @if($can('purchases.create'))
                <a href="{{ route('purchases.create', ['supplier_id' => $supplier->id]) }}" class="btn btn-success"><i class="bi bi-bag-plus"></i> Nueva compra</a>
            @endif
            @if($can('suppliers.edit'))
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
            @endif
            @if($can('suppliers.delete'))
                <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="m-0" onsubmit="return confirm('¿Eliminar el proveedor? Sus compras se conservan.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Comprado (histórico)</div><div class="fs-4 fw-bold">{{ $money($bought) }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Saldo por pagar</div><div class="fs-4 fw-bold {{ $payable > 0 ? 'text-danger' : 'text-success' }}">{{ $money($payable) }}</div></div></div></div>
        @if($supplier->notes)
            <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body small"><span class="text-muted">Observaciones:</span> {{ $supplier->notes }}</div></div></div>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Compra</th><th>Fecha</th><th>Factura</th><th>Estado</th><th class="text-end">Total</th><th class="text-end pe-3">Saldo</th></tr></thead>
                <tbody>
                    @forelse($purchases as $purchase)
                        <tr>
                            <td class="ps-3"><a href="{{ route('purchases.show', $purchase) }}" class="fw-semibold text-decoration-none">{{ $purchase->code }}</a></td>
                            <td class="small">{{ $purchase->purchased_at->format('d/m/Y') }}</td>
                            <td class="small">{{ $purchase->document_number ?: '—' }}</td>
                            <td><span class="badge bg-{{ $purchase->status_color }}">{{ $purchase->status_label }}</span></td>
                            <td class="text-end">{{ $money($purchase->total) }}</td>
                            <td class="text-end pe-3 {{ $purchase->balance > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $purchase->balance > 0 ? $money($purchase->balance) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Sin compras a este proveedor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3 d-flex justify-content-center">{{ $purchases->links() }}</div>
</div>
@endsection
