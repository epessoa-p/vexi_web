@extends('layouts.app')

@section('title', 'Gastos')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-wallet2"></i> Gastos</h1>
            <p class="text-muted mb-0">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · total {{ $money($total) }}</p>
        </div>
        @if($can('expenses.create'))
            <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Registrar gasto</a>
        @endif
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Categoría</label>
                <select name="category_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-search"></i> Filtrar</button>
                <a href="{{ route('expenses.index') }}" class="btn btn-light border" title="Mes actual"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Fecha</th><th>Concepto</th><th>Categoría</th><th>Pago</th><th>Responsable</th><th class="text-end">Monto</th><th class="pe-3"></th></tr></thead>
                            <tbody>
                                @forelse($expenses as $expense)
                                    <tr>
                                        <td class="ps-3 small text-nowrap">{{ $expense->spent_at->format('d/m/Y') }}</td>
                                        <td>
                                            {{ $expense->concept }}
                                            @if($expense->receipt_url)<a href="{{ $expense->receipt_url }}" target="_blank" class="ms-1" title="Comprobante"><i class="bi bi-paperclip"></i></a>@endif
                                        </td>
                                        <td class="small">{{ $expense->category?->name ?? '—' }}</td>
                                        <td class="small">{{ $expense->paymentMethod?->name ?? '—' }}@if($expense->cash_session_id)<div class="text-muted">desde caja</div>@endif</td>
                                        <td class="small text-muted">{{ $expense->user?->name }}</td>
                                        <td class="text-end fw-semibold">{{ $money($expense->amount) }}</td>
                                        <td class="pe-3 text-end text-nowrap">
                                            @if($can('expenses.edit'))
                                                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                            @endif
                                            @if($can('expenses.delete'))
                                                <form action="{{ route('expenses.destroy', $expense) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este gasto?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-5">Sin gastos en el período.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-center">{{ $expenses->links() }}</div>
        </div>
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Por categoría</h6>
                    @forelse($byCategory as $name => $amount)
                        <div class="mb-2 small">
                            <div class="d-flex justify-content-between"><span>{{ $name }}</span><span class="fw-semibold">{{ $money($amount) }}</span></div>
                            <div class="progress" style="height: 4px"><div class="progress-bar" style="width: {{ $total > 0 ? round($amount / $total * 100) : 0 }}%"></div></div>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">—</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
