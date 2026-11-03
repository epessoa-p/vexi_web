@extends('layouts.app')

@section('title', 'Caja')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-cash-coin"></i> Caja</h1>
            <p class="text-muted mb-0">Apertura, movimientos, arqueo y cierre de cada caja.</p>
        </div>
        @if($can('cajas.create'))
            <a href="{{ route('cajas.create') }}" class="btn btn-outline-secondary"><i class="bi bi-plus-lg"></i> Nueva caja</a>
        @endif
    </div>

    <div class="row g-3 mb-4">
        @forelse($cajas as $caja)
            @php $current = $caja->current; @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 {{ $current ? 'border-start border-success border-4' : '' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="mb-0">{{ $caja->name }}</h5>
                                <small class="text-muted">{{ $caja->branch?->name }}</small>
                            </div>
                            <span class="badge {{ $current ? 'bg-success' : 'bg-secondary' }} align-self-start">{{ $current ? 'Abierta' : 'Cerrada' }}</span>
                        </div>
                        @if($current)
                            <div class="small text-muted mt-2">Desde {{ $current->opened_at->format('d/m H:i') }} · {{ $current->opener?->name }}</div>
                            <div class="fs-4 fw-bold mt-1">{{ $money($current->expectedCash()) }} <small class="fs-6 text-muted fw-normal">en efectivo</small></div>
                            <a href="{{ route('cash.show', $current) }}" class="btn btn-primary btn-sm mt-2"><i class="bi bi-box-arrow-in-right"></i> Ver turno / cerrar</a>
                        @elseif($can('cash.create'))
                            <form action="{{ route('cash.open', $caja) }}" method="POST" class="mt-3 d-flex gap-2">
                                @csrf
                                <input type="number" name="opening_amount" step="0.01" min="0" class="form-control form-control-sm" placeholder="Saldo inicial" required>
                                <button class="btn btn-success btn-sm text-nowrap" type="submit"><i class="bi bi-unlock"></i> Abrir</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
                    No hay cajas registradas.
                    @if($can('cajas.create'))<a href="{{ route('cajas.create') }}">Crea la primera</a>.@endif
                </div></div>
            </div>
        @endforelse
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body pb-0"><h6 class="fw-bold"><i class="bi bi-clock-history"></i> Historial de cierres</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Caja</th><th>Apertura</th><th>Cierre</th><th class="text-end">Esperado</th><th class="text-end">Contado</th><th class="text-end pe-3">Diferencia</th></tr></thead>
                    <tbody>
                        @forelse($history as $session)
                            <tr>
                                <td class="ps-3"><a href="{{ route('cash.show', $session) }}" class="text-decoration-none">{{ $session->caja?->name }}</a></td>
                                <td class="small">{{ $session->opened_at->format('d/m/Y H:i') }}<div class="text-muted">{{ $session->opener?->name }}</div></td>
                                <td class="small">{{ $session->closed_at?->format('d/m/Y H:i') }}<div class="text-muted">{{ $session->closer?->name }}</div></td>
                                <td class="text-end">{{ $money($session->expected_cash) }}</td>
                                <td class="text-end">{{ $money($session->counted_cash) }}</td>
                                <td class="text-end pe-3 fw-semibold {{ (float) $session->difference < 0 ? 'text-danger' : ((float) $session->difference > 0 ? 'text-warning' : 'text-success') }}">
                                    {{ (float) $session->difference == 0 ? 'Cuadra' : $money($session->difference) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Sin cierres todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex justify-content-center">{{ $history->links() }}</div>
</div>
@endsection
