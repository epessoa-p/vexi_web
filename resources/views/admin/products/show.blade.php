@extends('layouts.app')

@section('title', $product->name)

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $num = fn ($value) => \App\Support\Stock::number($value);
    $total = $product->stockIn();
    $operation = fn (string $op) => route('stock-movements.create', ['operation' => $op, 'product_id' => $product->id, 'return' => 'product']);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @if($product->image_url)
                <img src="{{ $product->image_url }}" alt="" class="rounded border object-fit-cover" style="width: 72px; height: 72px;">
            @endif
            <div>
                <h1 class="mb-1">
                    {{ $product->name }}
                    @unless($product->active)<span class="badge bg-secondary fs-6 align-middle">Inactivo</span>@endunless
                </h1>
                <div class="text-muted">
                    {{ collect([$product->presentation, $product->brand, $product->category?->name])->filter()->implode(' · ') }}
                    @if($product->code) · Cód. {{ $product->code }} @endif
                    @if($product->barcode) · <i class="bi bi-upc"></i> {{ $product->barcode }} @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($can('inventory.create'))
                <a href="{{ $operation('in') }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down"></i> Entrada</a>
                <a href="{{ $operation('out') }}" class="btn btn-outline-danger"><i class="bi bi-box-arrow-up"></i> Salida</a>
                <a href="{{ $operation('adjustment') }}" class="btn btn-outline-warning"><i class="bi bi-clipboard-check"></i> Ajuste</a>
                @if($branches->count() > 1)
                    <a href="{{ $operation('transfer') }}" class="btn btn-outline-info"><i class="bi bi-arrow-left-right"></i> Transferir</a>
                @endif
            @endif
            @if($can('products.edit'))
                <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
            @endif
            @if($can('products.delete'))
                <form action="{{ route('products.destroy', $product) }}" method="POST" class="m-0" onsubmit="return confirm('¿Quitar {{ $product->name }} del catálogo? Su kardex se conserva.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-boxes"></i> Existencia</h6>
                    <div class="display-6 fw-bold {{ $total <= 0 ? 'text-danger' : ($product->isLowStock() ? 'text-warning' : '') }}">
                        {{ $num($total) }} <small class="fs-6 text-muted">{{ $product->unit_label }}</small>
                    </div>
                    @if($product->isLowStock())
                        <div class="text-warning small mb-2"><i class="bi bi-exclamation-triangle"></i> En o bajo el mínimo ({{ $num($product->min_stock) }})</div>
                    @endif
                    @if($product->stocks->count() > 1 || $branches->count() > 1)
                        <ul class="list-unstyled small mb-0 mt-2">
                            @foreach($product->stocks as $stock)
                                <li class="d-flex justify-content-between border-bottom py-1">
                                    <span>{{ $stock->branch?->name }}</span>
                                    <span class="fw-semibold {{ $stock->quantity < 0 ? 'text-danger' : '' }}">{{ $num($stock->quantity) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <hr>
                    <dl class="row small mb-0">
                        <dt class="col-6 text-muted fw-normal">Precio de compra</dt><dd class="col-6 text-end">{{ \App\Support\Money::format($product->purchase_price, $tenantCompany) }}</dd>
                        <dt class="col-6 text-muted fw-normal">Precio de venta</dt><dd class="col-6 text-end">{{ \App\Support\Money::format($product->sale_price, $tenantCompany) }}</dd>
                        <dt class="col-6 text-muted fw-normal">Valor del stock</dt><dd class="col-6 text-end">{{ \App\Support\Money::format(max($total, 0) * (float) $product->purchase_price, $tenantCompany) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-calendar2-x"></i> Lotes con existencia</h6>
                    @if(! $product->track_batches)
                        <p class="small text-muted mb-0">Este producto no controla lotes. Actívalo al editarlo si tiene vencimiento.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Lote</th><th>Sucursal</th><th>Fabricación</th><th>Vencimiento</th><th class="text-end">Cantidad</th></tr></thead>
                                <tbody>
                                    @forelse($batches as $batch)
                                        <tr>
                                            <td class="fw-semibold">{{ $batch->lot }}</td>
                                            <td class="small">{{ $batch->branch?->name }}</td>
                                            <td class="small">{{ $batch->manufactured_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td>
                                                {{ $batch->expires_at?->format('d/m/Y') ?? '—' }}
                                                @if($batch->isExpired())
                                                    <span class="badge bg-danger">Vencido</span>
                                                @elseif($batch->isExpiringSoon())
                                                    <span class="badge bg-warning text-dark">Por vencer</span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $num($batch->quantity) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-3">Sin lotes con existencia.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="fw-bold mb-0"><i class="bi bi-journal-text"></i> Kardex</h6>
            @if($branches->count() > 1)
                <form method="GET" class="d-flex gap-2">
                    <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todas las sucursales</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 mt-2">
                    <thead class="table-light">
                        <tr><th class="ps-3">Fecha</th><th>Tipo</th><th>Motivo</th><th>Sucursal</th><th>Lote</th><th class="text-end">Cantidad</th><th class="text-end">Saldo</th><th class="pe-3">Usuario</th></tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            @include('admin.products.partials.movement-row', ['movement' => $movement])
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex justify-content-center">{{ $movements->links() }}</div>
</div>
@endsection
