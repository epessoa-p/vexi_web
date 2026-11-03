@extends('layouts.app')

@section('title', 'Productos')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $num = fn ($value) => \App\Support\Stock::number($value);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-box-seam"></i> Productos</h1>
            <p class="text-muted mb-0">
                Farmacia e inventario.
                @if(! $limit['unlimited'])<span class="small">({{ $limit['usage'] }} de {{ $limit['max'] }} productos del plan)</span>@endif
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($can('inventory.create'))
                <a href="{{ route('stock-movements.create', ['operation' => 'in']) }}" class="btn btn-outline-success"><i class="bi bi-box-arrow-in-down"></i> Registrar entrada</a>
            @endif
            @if($can('products.create'))
                @if($limit['reached'])
                    <button class="btn btn-primary" disabled title="Alcanzaste el límite de productos de tu plan"><i class="bi bi-plus-lg"></i> Nuevo producto</button>
                @else
                    <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo producto</a>
                @endif
            @endif
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre, marca, código o código de barras" autofocus>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Categoría</label>
                <select name="category_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Activos</option>
                    <option value="low" @selected(request('status') === 'low')>Stock mínimo</option>
                    <option value="out" @selected(request('status') === 'out')>Agotados</option>
                    <option value="expiring" @selected(request('status') === 'expiring')>Con lotes por vencer</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
            </div>
            @if($branches->count() > 1)
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Existencia en</label>
                    <select name="branch_id" class="form-select">
                        <option value="">Todas las sucursales</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-search"></i> Buscar</button>
                @if(request()->hasAny(['q', 'category_id', 'status', 'branch_id']))
                    <a href="{{ route('products.index') }}" class="btn btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Producto</th>
                            <th>Categoría</th>
                            <th class="text-end">Existencia</th>
                            <th class="text-end">Mínimo</th>
                            <th class="text-end">Precio venta</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $stock = $product->stockIn($branchId);
                                $low = $product->isLowStock($branchId);
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($product->image_url)
                                            <img src="{{ $product->image_url }}" alt="" class="rounded border object-fit-cover" style="width: 36px; height: 36px;">
                                        @else
                                            <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 36px; height: 36px;"><i class="bi bi-box"></i></div>
                                        @endif
                                        <div>
                                            <a href="{{ route('products.show', $product) }}" class="fw-semibold text-decoration-none">{{ $product->name }}</a>
                                            @if($product->track_batches)<span class="badge bg-light text-muted border" title="Controla lotes y vencimientos">Lotes</span>@endif
                                            <div class="small text-muted">{{ collect([$product->presentation, $product->brand, $product->code])->filter()->implode(' · ') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">{{ $product->category?->name ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <span class="fw-semibold {{ $stock <= 0 ? 'text-danger' : ($low ? 'text-warning' : '') }}">{{ $num($stock) }}</span>
                                    <small class="text-muted">{{ $product->unit }}</small>
                                    @if($stock <= 0)
                                        <div><span class="badge bg-danger">Agotado</span></div>
                                    @elseif($low)
                                        <div><span class="badge bg-warning text-dark">Stock mínimo</span></div>
                                    @endif
                                </td>
                                <td class="text-end small text-muted">{{ (float) $product->min_stock ? $num($product->min_stock) : '—' }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($product->sale_price, $tenantCompany) }}</td>
                                <td class="text-end pe-3 text-nowrap">
                                    <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-outline-secondary" title="Kardex"><i class="bi bi-journal-text"></i></a>
                                    @if($can('products.edit'))
                                        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">
                                {{ request()->hasAny(['q', 'category_id', 'status']) ? 'Ningún producto coincide con el filtro.' : 'Todavía no hay productos. Crea el primero.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $products->links() }}</div>
</div>
@endsection
