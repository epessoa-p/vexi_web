@extends('layouts.app')

@section('title', 'Movimiento de inventario')

@section('page')
@php
    $operations = \App\Http\Controllers\Admin\StockMovementController::OPERATIONS;
    $reasons = \App\Models\StockMovement::REASONS;
    $op = old('operation', $operation);
    $productData = $product ? [
        'id' => $product->id, 'name' => $product->name, 'label' => $product->label, 'unit' => $product->unit_label,
        'track_batches' => $product->track_batches, 'purchase_price' => (float) $product->purchase_price,
    ] : null;
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-left-right"></i> Movimiento de inventario</h1>
            <p class="text-muted mb-0">Cada movimiento queda en el kardex del producto y no se puede borrar.</p>
        </div>
        <a href="{{ $product && request('return') === 'product' ? route('products.show', $product) : route('stock-movements.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach($operations as $value => $label)
            @continue($value === 'transfer' && $branches->count() < 2)
            <li class="nav-item">
                <a class="nav-link {{ $op === $value ? 'active' : '' }}" href="{{ route('stock-movements.create', array_filter(['operation' => $value, 'product_id' => $product?->id, 'return' => request('return')])) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <form action="{{ route('stock-movements.store') }}" method="POST" class="card border-0 shadow-sm" id="movement-form">
        @csrf
        <input type="hidden" name="operation" value="{{ $op }}">
        @if(request('return'))<input type="hidden" name="return" value="{{ request('return') }}">@endif
        <input type="hidden" name="product_id" id="product-id" value="{{ old('product_id', $product?->id) }}">

        <div class="card-body p-4 row g-3">
            {{-- Producto --}}
            <div class="col-12">
                <label class="form-label">Producto <span class="text-danger">*</span></label>
                <div id="product-selected" class="d-flex align-items-center justify-content-between border rounded p-2 {{ $product ? '' : 'd-none' }}">
                    <div><i class="bi bi-box-seam text-success"></i> <span class="fw-semibold" id="product-name">{{ $product?->label }}</span> <small class="text-muted" id="product-stock"></small></div>
                    <button type="button" class="btn btn-sm btn-link text-decoration-none" id="product-change">Cambiar</button>
                </div>
                <div id="product-picker" class="position-relative {{ $product ? 'd-none' : '' }}">
                    <input type="search" id="product-search" class="form-control @error('product_id') is-invalid @enderror" placeholder="Busca por nombre, código o escanea el código de barras…" autocomplete="off">
                    <div id="product-results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1050; max-height: 300px; overflow-y: auto;"></div>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">{{ $op === 'transfer' ? 'Sucursal de origen' : 'Sucursal' }} <span class="text-danger">*</span></label>
                <select name="branch_id" id="branch-id" class="form-select @error('branch_id') is-invalid @enderror">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($op === 'transfer')
                <div class="col-md-6">
                    <label class="form-label">Sucursal de destino <span class="text-danger">*</span></label>
                    <select name="to_branch_id" class="form-select @error('to_branch_id') is-invalid @enderror">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('to_branch_id', $branches->get(1)?->id) === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(in_array($op, ['in', 'out'], true))
                <div class="col-md-6">
                    <label class="form-label">Motivo</label>
                    <select name="reason" class="form-select">
                        @foreach($op === 'in' ? \App\Models\StockMovement::MANUAL_IN_REASONS : \App\Models\StockMovement::MANUAL_OUT_REASONS as $reason)
                            <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reasons[$reason] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-6">
                <label class="form-label">
                    {{ $op === 'adjustment' ? 'Cantidad contada' : 'Cantidad' }} <span class="text-danger">*</span>
                    <small class="text-muted" id="unit-label">{{ $product?->unit_label }}</small>
                </label>
                <input type="number" step="0.01" min="0" name="quantity" class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" required>
                @if($op === 'adjustment')<div class="form-text">Se registra la diferencia contra la existencia del sistema.</div>@endif
            </div>

            {{-- Lotes: al entrar se indica; al salir/ajustar/transferir se elige (o FEFO). --}}
            @if($op === 'in')
                <div class="col-md-4 batch-in">
                    <label class="form-label">Lote</label>
                    <input type="text" name="lot" class="form-control @error('lot') is-invalid @enderror" value="{{ old('lot') }}" maxlength="60">
                </div>
                <div class="col-md-4 batch-in">
                    <label class="form-label">Vencimiento</label>
                    <input type="date" name="expires_at" class="form-control @error('expires_at') is-invalid @enderror" value="{{ old('expires_at') }}">
                </div>
                <div class="col-md-4 batch-in">
                    <label class="form-label">Fabricación</label>
                    <input type="date" name="manufactured_at" class="form-control" value="{{ old('manufactured_at') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Costo unitario ({{ \App\Support\Money::symbol($tenantCompany) }})</label>
                    <input type="number" step="0.01" min="0" name="unit_cost" id="unit-cost" class="form-control" value="{{ old('unit_cost', $product?->purchase_price) }}">
                    <div class="form-text">En una compra, actualiza el precio de compra del producto.</div>
                </div>
            @else
                <div class="col-md-6 batch-out">
                    <label class="form-label">Lote</label>
                    <select name="product_batch_id" id="batch-id" class="form-select @error('product_batch_id') is-invalid @enderror">
                        <option value="">{{ $op === 'adjustment' ? 'Elegir lote…' : 'Automático (primero el que vence antes)' }}</option>
                    </select>
                </div>
            @endif

            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="255" placeholder="Nº de factura, proveedor, motivo…">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Registrar {{ mb_strtolower($operations[$op]) }}</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const searchUrl = @json(route('products.search'));
    const operation = @json($op);
    const oldBatch = @json(old('product_batch_id'));
    let product = @json($productData);
    let batches = [];
    let stocks = {};

    const $ = (id) => document.getElementById(id);
    const branch = $('branch-id');
    const batchSelect = $('batch-id');

    function renderProduct() {
        if (!product) return;
        $('product-id').value = product.id;
        $('product-name').textContent = product.label;
        $('unit-label').textContent = product.unit || '';
        $('product-selected').classList.remove('d-none');
        $('product-picker').classList.add('d-none');
        document.querySelectorAll('.batch-in, .batch-out').forEach((el) => el.classList.toggle('d-none', !product.track_batches));
        if ($('unit-cost') && !$('unit-cost').value) $('unit-cost').value = product.purchase_price || '';
        renderBranch();
    }

    // Existencia y lotes de la sucursal elegida.
    function renderBranch() {
        const stock = stocks[branch.value] ?? 0;
        $('product-stock').textContent = product ? '· existencia en la sucursal: ' + stock + ' ' + (product.unit || '') : '';
        if (!batchSelect) return;
        const first = batchSelect.options[0];
        batchSelect.innerHTML = '';
        batchSelect.add(first);
        batches.filter((b) => String(b.branch_id) === branch.value).forEach(function (b) {
            const text = b.lot + ' · ' + b.quantity + (b.expires_at ? ' · vence ' + b.expires_at : '') + (b.expired ? ' (VENCIDO)' : '');
            batchSelect.add(new Option(text, b.id, false, String(b.id) === String(oldBatch)));
        });
    }

    async function load(term) {
        const response = await fetch(searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } });
        return response.ok ? response.json() : [];
    }

    function choose(item) {
        product = item;
        batches = item.batches || [];
        stocks = item.stocks || {};
        $('product-results').classList.add('d-none');
        renderProduct();
    }

    let timer = null;
    $('product-search').addEventListener('input', function () {
        clearTimeout(timer);
        const term = this.value.trim();
        if (term.length < 2) { $('product-results').classList.add('d-none'); return; }
        timer = setTimeout(async function () {
            const items = await load(term);
            const results = $('product-results');
            results.innerHTML = items.length ? '' : '<div class="list-group-item text-muted small">Sin resultados</div>';
            // Un código de barras exacto se elige solo (lector de código de barras).
            if (items.length === 1 && (items[0].code === term || items[0].barcode === term)) { choose(items[0]); return; }
            items.forEach(function (item) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action d-flex justify-content-between';
                button.innerHTML = '<span></span><small class="text-muted"></small>';
                button.querySelector('span').textContent = item.label;
                button.querySelector('small').textContent = 'Stock ' + item.stock + ' ' + item.unit;
                button.addEventListener('click', () => choose(item));
                results.appendChild(button);
            });
            results.classList.remove('d-none');
        }, 250);
    });

    $('product-change').addEventListener('click', function () {
        product = null;
        $('product-id').value = '';
        $('product-selected').classList.add('d-none');
        $('product-picker').classList.remove('d-none');
        $('product-search').value = '';
        $('product-search').focus();
    });

    branch.addEventListener('change', renderBranch);

    // Producto preseleccionado: traer existencia y lotes actualizados.
    if (product) {
        load(product.name).then(function (items) {
            const match = items.find((i) => i.id === product.id);
            if (match) choose(match); else renderProduct();
        });
    } else {
        document.querySelectorAll('.batch-in, .batch-out').forEach((el) => el.classList.add('d-none'));
        $('product-search').focus();
    }
})();
</script>
@endsection
