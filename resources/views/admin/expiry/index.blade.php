@extends('layouts.app')

@section('title', 'Vencimientos')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $num = fn ($value) => \App\Support\Stock::number($value);
    $today = now()->startOfDay();
@endphp
<div class="container-fluid">
    <div class="mb-3">
        <h1 class="mb-1"><i class="bi bi-hourglass-bottom"></i> Vencimientos</h1>
        <p class="text-muted mb-0">Lotes con existencia vencidos o que vencen en los próximos {{ config('vexi.expiry_alert_days') }} días.</p>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><a class="nav-link {{ $tab === 'expiring' ? 'active' : '' }}" href="{{ route('expiry.index') }}">Por vencer <span class="badge bg-warning text-dark">{{ $counts['expiring'] }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'expired' ? 'active' : '' }}" href="{{ route('expiry.index', ['tab' => 'expired']) }}">Vencidos <span class="badge bg-danger">{{ $counts['expired'] }}</span></a></li>
    </ul>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Vencimiento</th><th>Producto</th><th>Lote</th><th>Sucursal</th><th class="text-end">Cantidad</th><th class="text-end">Valor</th><th class="text-end pe-3"></th></tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            @php $days = (int) $today->diffInDays($batch->expires_at, false); @endphp
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    <div class="fw-semibold">{{ $batch->expires_at->format('d/m/Y') }}</div>
                                    @if($days < 0)
                                        <span class="badge bg-danger">hace {{ abs($days) }} días</span>
                                    @else
                                        <span class="badge {{ $days <= 15 ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">{{ $days === 0 ? 'hoy' : "en $days días" }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('products.show', $batch->product_id) }}" class="fw-semibold text-decoration-none">{{ $batch->product?->name }}</a>
                                    <div class="small text-muted">{{ $batch->product?->presentation }}</div>
                                </td>
                                <td>{{ $batch->lot }}</td>
                                <td class="small">{{ $batch->branch?->name }}</td>
                                <td class="text-end">{{ $num($batch->quantity) }} <small class="text-muted">{{ $batch->product?->unit }}</small></td>
                                <td class="text-end small">{{ \App\Support\Money::format((float) $batch->quantity * (float) $batch->product?->purchase_price, $tenantCompany) }}</td>
                                <td class="text-end pe-3">
                                    @if($tab === 'expired' && $can('inventory.edit'))
                                        <form action="{{ route('expiry.write-off', $batch) }}" method="POST" class="m-0"
                                              onsubmit="return confirm('¿Dar de baja {{ $num($batch->quantity) }} de {{ $batch->product?->name }} (lote {{ $batch->lot }})?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash3"></i> Dar de baja</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">{{ $tab === 'expired' ? 'No hay lotes vencidos con existencia.' : 'Nada vence en los próximos días.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
