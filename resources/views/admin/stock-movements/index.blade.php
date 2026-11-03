@extends('layouts.app')

@section('title', 'Movimientos de inventario')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-left-right"></i> Movimientos de inventario</h1>
            <p class="text-muted mb-0">Historial de entradas, salidas, ajustes y transferencias de todos los productos.</p>
        </div>
        @if($can('inventory.create'))
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('stock-movements.create', ['operation' => 'in']) }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down"></i> Entrada</a>
                <a href="{{ route('stock-movements.create', ['operation' => 'out']) }}" class="btn btn-outline-danger"><i class="bi bi-box-arrow-up"></i> Salida</a>
                <a href="{{ route('stock-movements.create', ['operation' => 'adjustment']) }}" class="btn btn-outline-warning"><i class="bi bi-clipboard-check"></i> Ajuste</a>
                @if($branches->count() > 1)
                    <a href="{{ route('stock-movements.create', ['operation' => 'transfer']) }}" class="btn btn-outline-info"><i class="bi bi-arrow-left-right"></i> Transferencia</a>
                @endif
            </div>
        @endif
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            @if($filterProduct)
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Producto</label>
                    <div class="form-control bg-light">{{ $filterProduct->name }}</div>
                    <input type="hidden" name="product_id" value="{{ $filterProduct->id }}">
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Tipo</label>
                <select name="type" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Models\StockMovement::TYPES as $value => [$label])
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Motivo</label>
                <select name="reason" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Models\StockMovement::REASONS as $value => $label)
                        <option value="{{ $value }}" @selected(request('reason') === $value)>{{ $label }}</option>
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
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-outline-primary flex-fill" type="submit" title="Filtrar"><i class="bi bi-search"></i></button>
                @if(request()->hasAny(['product_id', 'type', 'reason', 'branch_id', 'from', 'to']))
                    <a href="{{ route('stock-movements.index') }}" class="btn btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Fecha</th><th>Producto</th><th>Tipo</th><th>Motivo</th><th>Sucursal</th><th>Lote</th><th class="text-end">Cantidad</th><th class="text-end">Saldo</th><th class="pe-3">Usuario</th></tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            @include('admin.products.partials.movement-row', ['movement' => $movement, 'showProduct' => true])
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-5">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $movements->links() }}</div>
</div>
@endsection
