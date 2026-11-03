@extends('layouts.app')

@section('title', 'Turno de caja')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
@endphp
<div class="container-fluid" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $session->caja?->name }} <span class="badge {{ $session->isOpen() ? 'bg-success' : 'bg-secondary' }} fs-6 align-middle">{{ $session->isOpen() ? 'Abierta' : 'Cerrada' }}</span></h1>
            <div class="text-muted">
                Apertura {{ $session->opened_at->format('d/m/Y H:i') }} por {{ $session->opener?->name }} con {{ $money($session->opening_amount) }}
                @if($session->closed_at) · Cierre {{ $session->closed_at->format('d/m/Y H:i') }} por {{ $session->closer?->name }} @endif
            </div>
        </div>
        <a href="{{ route('cash.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cajas</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-calculator"></i> {{ $session->isOpen() ? 'Resumen del turno' : 'Arqueo' }}</h6>
                    <dl class="row small mb-0">
                        <dt class="col-7 text-muted fw-normal">Saldo inicial</dt><dd class="col-5 text-end">{{ $money($session->opening_amount) }}</dd>
                        @foreach($totals as $total)
                            <dt class="col-7 text-muted fw-normal">{{ $total['method'] }}</dt>
                            <dd class="col-5 text-end {{ $total['amount'] < 0 ? 'text-danger' : '' }}">{{ $money($total['amount']) }}</dd>
                        @endforeach
                        <dt class="col-7 fw-semibold border-top pt-1">Efectivo esperado</dt><dd class="col-5 text-end fw-semibold border-top pt-1">{{ $money($expected) }}</dd>
                        @unless($session->isOpen())
                            <dt class="col-7 text-muted fw-normal">Efectivo contado</dt><dd class="col-5 text-end">{{ $money($session->counted_cash) }}</dd>
                            <dt class="col-7 fw-semibold">Diferencia</dt>
                            <dd class="col-5 text-end fw-bold {{ (float) $session->difference < 0 ? 'text-danger' : ((float) $session->difference > 0 ? 'text-warning' : 'text-success') }}">
                                {{ (float) $session->difference == 0 ? 'Cuadra' : $money($session->difference) }}
                            </dd>
                        @endunless
                    </dl>
                    @if($session->notes)<p class="small text-muted mt-2 mb-0" style="white-space: pre-line">{{ $session->notes }}</p>@endif
                </div>
            </div>

            @if($session->isOpen() && $can('cash.create'))
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-plus-slash-minus"></i> Ingreso / egreso / retiro</h6>
                        <form action="{{ route('cash.movement', $session) }}" method="POST" class="d-flex flex-column gap-2">
                            @csrf
                            <div class="btn-group btn-group-sm" role="group">
                                @foreach(['income' => 'Ingreso', 'expense' => 'Egreso', 'withdrawal' => 'Retiro'] as $value => $label)
                                    <input type="radio" class="btn-check" name="type" id="type-{{ $value }}" value="{{ $value }}" @checked($value === 'income')>
                                    <label class="btn btn-outline-secondary" for="type-{{ $value }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            <select name="payment_method_id" class="form-select form-select-sm">
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" name="amount" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="Monto" required>
                            <input type="text" name="description" class="form-control form-control-sm" placeholder="Concepto (ej. cambio, depósito al banco)" maxlength="255" required>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Registrar</button>
                        </form>
                        <p class="small text-muted mt-2 mb-0">Para un gasto con categoría y comprobante usa <a href="{{ route('expenses.create') }}">Gastos</a>.</p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm border-start border-danger border-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-lock"></i> Arqueo y cierre</h6>
                        <form action="{{ route('cash.close', $session) }}" method="POST" class="d-flex flex-column gap-2"
                              onsubmit="return confirm('¿Cerrar la caja? Después no se pueden registrar más movimientos en este turno.')">
                            @csrf
                            <label class="small text-muted">Efectivo contado en caja</label>
                            <input type="number" name="counted_cash" step="0.01" min="0" class="form-control" required>
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Observaciones del cierre" maxlength="500">
                            <button class="btn btn-danger" type="submit">Cerrar caja</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Hora</th><th>Tipo</th><th>Concepto</th><th>Método</th><th>Usuario</th><th class="text-end pe-3">Monto</th></tr></thead>
                        <tbody>
                            @forelse($movements as $movement)
                                <tr>
                                    <td class="ps-3 small">{{ $movement->created_at->format('d/m H:i') }}</td>
                                    <td><span class="badge bg-{{ $movement->type_color }}">{{ $movement->type_label }}</span></td>
                                    <td class="small">{{ $movement->description }}</td>
                                    <td class="small">{{ $movement->paymentMethod?->name }}</td>
                                    <td class="small text-muted">{{ $movement->user?->name }}</td>
                                    <td class="text-end pe-3 fw-semibold {{ $movement->amount < 0 ? 'text-danger' : 'text-success' }}">{{ $money($movement->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">Sin movimientos en este turno.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
