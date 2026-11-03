@extends('layouts.app')

@section('title', 'Nueva compra')

@section('page')
@php
    $symbol = \App\Support\Money::symbol($tenantCompany);
    $oldItems = collect(old('items', []))->values();
    $receiveNow = old('_token') ? (bool) old('receive_now') : true;
@endphp
<div class="container-fluid" style="max-width: 1300px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-bag-plus"></i> Nueva compra</h1>
        <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Compras</a>
    </div>

    <form action="{{ route('purchases.store') }}" method="POST" enctype="multipart/form-data" id="purchase-form">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">Seleccionar…</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', request('supplier_id')) === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @if(auth()->user()->hasPermissionInCompany('suppliers.create', $tenantCompany))
                        <div class="form-text"><a href="{{ route('suppliers.create', ['return' => 'purchase']) }}">+ Nuevo proveedor</a></div>
                    @endif
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="purchased_at" class="form-control @error('purchased_at') is-invalid @enderror" value="{{ old('purchased_at', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nº de factura</label>
                    <input type="text" name="document_number" class="form-control" value="{{ old('document_number') }}" maxlength="60">
                </div>
                @if($branches->count() > 1)
                    <div class="col-md-2">
                        <label class="form-label">Recibe en</label>
                        <select name="branch_id" class="form-select">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-2">
                    <label class="form-label">Factura (archivo)</label>
                    <input type="file" name="invoice_file" accept="image/*,application/pdf" class="form-control @error('invoice_file') is-invalid @enderror">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex gap-3 align-items-center flex-wrap mb-2">
                    <div class="position-relative flex-grow-1">
                        <input type="search" id="product-search" class="form-control" placeholder="Agregar producto: busca por nombre o escanea el código de barras…" autocomplete="off">
                        <div id="product-results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1050; max-height: 300px; overflow-y: auto;"></div>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="receive_now" value="1" id="receive-now" @checked($receiveNow)>
                        <label class="form-check-label" for="receive-now">La mercadería llegó (recibir ahora)</label>
                    </div>
                </div>
                @error('items')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror
                @error('lot')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th><th style="width: 7rem">Cantidad</th><th style="width: 8rem">Costo unit.</th><th style="width: 7rem">Desc.</th>
                                <th class="batch-col">Lote / vencimiento</th><th class="text-end" style="width: 8rem">Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody id="items"></tbody>
                    </table>
                </div>
                <p class="text-center text-muted small py-3 mb-0" id="items-empty">Busca y agrega los productos comprados.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body row g-2 align-items-end">
                        <h6 class="fw-bold mb-1"><i class="bi bi-cash-stack"></i> Pago al proveedor</h6>
                        <div class="col-md-4">
                            <label class="form-label small">Monto pagado ahora</label>
                            <input type="number" name="paid_amount" id="paid-amount" step="0.01" min="0" class="form-control" value="{{ old('paid_amount') }}" placeholder="0 = a crédito">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Método</label>
                            <select name="payment_method_id" class="form-select @error('payment_method_id') is-invalid @enderror">
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Referencia</label>
                            <input type="text" name="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference') }}" maxlength="80">
                        </div>
                        @if($sessions->isNotEmpty())
                            <div class="col-md-6">
                                <label class="form-label small">¿Sale de una caja?</label>
                                <select name="cash_session_id" class="form-select">
                                    <option value="">No (banco u otro medio)</option>
                                    @foreach($sessions as $session)
                                        <option value="{{ $session->id }}" @selected((string) old('cash_session_id') === (string) $session->id)>Sí, de {{ $session->caja?->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label small">Vencimiento del saldo</label>
                            <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}">
                        </div>
                        <div class="col-12">
                            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Observaciones" maxlength="2000" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between small"><span>Subtotal</span><span id="subtotal">0</span></div>
                        <div class="d-flex justify-content-between align-items-center small my-1">
                            <span>Descuento general</span>
                            <input type="number" name="discount" id="discount" step="0.01" min="0" class="form-control form-control-sm text-end" style="width: 7rem" value="{{ old('discount', 0) }}">
                        </div>
                        <div class="d-flex justify-content-between align-items-center small my-1">
                            <span>Impuesto (%)</span>
                            <input type="number" name="tax_rate" id="tax-rate" step="0.01" min="0" max="100" class="form-control form-control-sm text-end" style="width: 7rem" value="{{ old('tax_rate', 0) }}">
                        </div>
                        <div class="d-flex justify-content-between small"><span>Impuesto</span><span id="tax">0</span></div>
                        <div class="d-flex justify-content-between fs-4 fw-bold border-top pt-2 mt-2"><span>Total</span><span id="total">0</span></div>
                        <div class="d-flex justify-content-between small text-danger" id="balance-row"><span>Queda por pagar</span><span id="balance">0</span></div>
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3" id="submit" disabled><i class="bi bi-check2-circle"></i> Registrar compra</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="row-template">
    <tr class="item-row">
        <td>
            <div class="fw-semibold item-label"></div>
            <input type="hidden" data-name="product_id">
            <input type="hidden" data-name="label">
            <input type="hidden" data-name="track">
        </td>
        <td><input type="number" class="form-control form-control-sm" data-name="quantity" min="0.01" step="0.01" required></td>
        <td><input type="number" class="form-control form-control-sm" data-name="unit_cost" min="0" step="0.01" required></td>
        <td><input type="number" class="form-control form-control-sm" data-name="discount" min="0" step="0.01"></td>
        <td class="batch-col">
            <div class="d-flex gap-1 batch-fields">
                <input type="text" class="form-control form-control-sm" data-name="lot" placeholder="Lote" maxlength="60">
                <input type="date" class="form-control form-control-sm" data-name="expires_at" title="Vencimiento">
            </div>
            <span class="small text-muted no-batch">—</span>
        </td>
        <td class="text-end fw-semibold row-total">0</td>
        <td><button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row" title="Quitar"><i class="bi bi-x-circle"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const symbol = @json($symbol);
    const searchUrl = @json(route('products.search'));
    const money = (n) => symbol + ' ' + (Number(n) || 0).toFixed(2);
    const round = (n) => Math.round((Number(n) || 0) * 100) / 100;
    const $ = (id) => document.getElementById(id);
    let index = 0;

    function addRow(data) {
        const row = $('row-template').content.firstElementChild.cloneNode(true);
        const i = index++;
        row.querySelectorAll('[data-name]').forEach(function (input) {
            input.name = 'items[' + i + '][' + input.dataset.name + ']';
            if (data[input.dataset.name] !== undefined && data[input.dataset.name] !== null) input.value = data[input.dataset.name];
        });
        row.querySelector('.item-label').textContent = data.label;
        row.dataset.track = data.track ? '1' : '';
        row.addEventListener('input', totals);
        row.querySelector('.remove-row').addEventListener('click', () => { row.remove(); totals(); });
        $('items').appendChild(row);
        refreshBatches();
        totals();
        row.querySelector('[data-name="quantity"]').focus();
    }

    // Lote y vencimiento solo cuando se recibe ahora y el producto controla lotes.
    function refreshBatches() {
        const receive = $('receive-now').checked;
        document.querySelectorAll('.batch-col').forEach((el) => el.classList.toggle('d-none', !receive));
        document.querySelectorAll('.item-row').forEach(function (row) {
            const show = receive && row.dataset.track === '1';
            row.querySelector('.batch-fields').classList.toggle('d-none', !show);
            row.querySelector('.no-batch').classList.toggle('d-none', show);
            row.querySelectorAll('.batch-fields input').forEach((input) => input.required = show);
        });
    }

    function totals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(function (row) {
            const get = (name) => Number(row.querySelector('[data-name="' + name + '"]').value) || 0;
            const total = Math.max(round(get('quantity') * get('unit_cost') - get('discount')), 0);
            row.querySelector('.row-total').textContent = money(total);
            subtotal += total;
        });
        subtotal = round(subtotal);
        const discount = Math.min(round($('discount').value), subtotal);
        const tax = round((subtotal - discount) * (Number($('tax-rate').value) || 0) / 100);
        const total = round(subtotal - discount + tax);
        const paid = round($('paid-amount').value);
        $('subtotal').textContent = money(subtotal);
        $('tax').textContent = money(tax);
        $('total').textContent = money(total);
        $('balance').textContent = money(Math.max(total - paid, 0));
        $('balance-row').classList.toggle('d-none', paid >= total);
        const count = document.querySelectorAll('.item-row').length;
        $('items-empty').classList.toggle('d-none', count > 0);
        $('submit').disabled = count === 0;
    }

    ['discount', 'tax-rate', 'paid-amount'].forEach((id) => $(id).addEventListener('input', totals));
    $('receive-now').addEventListener('change', refreshBatches);

    let timer = null;
    $('product-search').addEventListener('input', function () {
        clearTimeout(timer);
        const term = this.value.trim();
        const results = $('product-results');
        if (term.length < 2) { results.classList.add('d-none'); return; }
        timer = setTimeout(async () => {
            const items = await fetch(searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } }).then((r) => r.ok ? r.json() : []);
            const pick = (p) => {
                addRow({ product_id: p.id, label: p.label, track: p.track_batches ? 1 : '', quantity: 1, unit_cost: p.purchase_price, discount: '' });
                this.value = '';
                results.classList.add('d-none');
            };
            const exact = items.find((p) => p.barcode === term || p.code === term);
            if (exact) return pick(exact);
            results.innerHTML = items.length ? '' : '<div class="list-group-item text-muted small">Sin resultados</div>';
            items.forEach(function (p) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'list-group-item list-group-item-action d-flex justify-content-between';
                b.innerHTML = '<span></span><small class="text-muted"></small>';
                b.querySelector('span').textContent = p.label;
                b.querySelector('small').textContent = 'Stock ' + p.stock + ' · último costo ' + money(p.purchase_price);
                b.addEventListener('click', () => pick(p));
                results.appendChild(b);
            });
            results.classList.remove('d-none');
        }, 250);
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('#product-results, #product-search')) $('product-results').classList.add('d-none'); });

    // Tras un error de validación se reconstruyen las filas enviadas.
    @json($oldItems).forEach((item) => addRow(item));
    refreshBatches();
    totals();
})();
</script>
@endsection
