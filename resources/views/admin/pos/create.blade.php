@extends('layouts.app')

@section('title', 'Nueva venta')

@section('page')
@php
    $symbol = \App\Support\Money::symbol($tenantCompany);
    $decimals = config('vexi.currencies.' . ($tenantCompany?->currency ?? 'BOB') . '.decimals', 2);
    $cash = $paymentMethods->firstWhere('type', 'cash') ?? $paymentMethods->first();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-cart-plus"></i> Nueva venta</h1>
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="bi bi-receipt"></i> Ventas</a>
    </div>

    @if($cashEnabled && $sessions->isEmpty())
        <div class="alert alert-warning d-flex justify-content-between align-items-center">
            <span><i class="bi bi-exclamation-triangle"></i> No hay ninguna caja abierta: abre una para poder cobrar.</span>
            @if(auth()->user()->hasPermissionInCompany('cash.view', $tenantCompany))
                <a href="{{ route('cash.index') }}" class="btn btn-sm btn-dark">Ir a caja</a>
            @endif
        </div>
    @endif

    <form action="{{ route('pos.store') }}" method="POST" id="pos-form">
        @csrf
        <div class="row g-3">
            {{-- Columna izquierda: cliente y búsqueda de ítems --}}
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label small text-muted mb-1">Cliente <span class="text-muted">(opcional: consumidor final)</span></label>
                                @include('admin.clients.partials.picker', ['selectedClient' => $selectedClient, 'prefix' => 'pos'])
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-1">Mascota</label>
                                <select name="pet_id" id="pos-pet" class="form-select" disabled>
                                    <option value="">—</option>
                                </select>
                            </div>
                        </div>
                        <div id="billable" class="mt-3 d-none">
                            <div class="small fw-semibold text-warning-emphasis mb-1"><i class="bi bi-clipboard2-pulse"></i> Pendiente de cobro</div>
                            <div id="billable-list" class="d-flex flex-column gap-1"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 pt-3">
                        <ul class="nav nav-tabs card-header-tabs">
                            @if($withInventory)
                                <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-products"><i class="bi bi-box-seam"></i> Productos</button></li>
                            @endif
                            <li class="nav-item"><button type="button" class="nav-link {{ $withInventory ? '' : 'active' }}" data-bs-toggle="tab" data-bs-target="#tab-services"><i class="bi bi-card-checklist"></i> Servicios</button></li>
                            <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-other"><i class="bi bi-plus-square"></i> Otro concepto</button></li>
                        </ul>
                    </div>
                    <div class="card-body tab-content">
                        @if($withInventory)
                            <div class="tab-pane fade show active" id="tab-products">
                                <input type="search" id="product-search" class="form-control mb-2" placeholder="Busca por nombre o escanea el código de barras…" autocomplete="off">
                                <div id="product-results" class="list-group" style="max-height: 320px; overflow-y: auto;"></div>
                            </div>
                        @endif
                        <div class="tab-pane fade {{ $withInventory ? '' : 'show active' }}" id="tab-services">
                            <input type="search" id="service-filter" class="form-control mb-2" placeholder="Filtrar servicios…">
                            <div class="list-group" id="service-list" style="max-height: 320px; overflow-y: auto;">
                                @foreach($services as $service)
                                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" data-service='@json(['id' => $service->id, 'name' => $service->name, 'price' => (float) $service->price])'>
                                        <span>{{ $service->name }}</span>
                                        <span class="text-muted">{{ \App\Support\Money::format($service->price, $tenantCompany) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-other">
                            <div class="row g-2">
                                <div class="col-md-8"><input type="text" id="other-description" class="form-control" placeholder="Concepto" maxlength="255"></div>
                                <div class="col-md-2"><input type="number" id="other-price" class="form-control" placeholder="Precio" min="0" step="0.01"></div>
                                <div class="col-md-2"><button type="button" class="btn btn-outline-primary w-100" id="other-add">Agregar</button></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Columna derecha: carrito y cobro --}}
            <div class="col-xl-5">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Ítem</th><th style="width: 4.5rem">Cant.</th><th style="width: 6rem">Precio</th><th style="width: 5.5rem">Desc.</th><th class="text-end pe-2">Total</th><th></th></tr></thead>
                            <tbody id="cart"></tbody>
                        </table>
                        <p class="text-center text-muted small py-4 mb-0" id="cart-empty">Agrega productos, servicios o lo atendido.</p>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between small"><span>Subtotal</span><span id="subtotal">0</span></div>
                        <div class="d-flex justify-content-between align-items-center small my-1">
                            <span>Descuento general</span>
                            <input type="number" name="discount" id="discount" class="form-control form-control-sm text-end" style="width: 7rem" min="0" step="0.01" value="{{ old('discount', 0) }}">
                        </div>
                        <div class="d-flex justify-content-between fs-4 fw-bold"><span>Total</span><span id="total">0</span></div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0"><i class="bi bi-cash-stack"></i> Pago</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="add-payment"><i class="bi bi-plus-lg"></i> Pago mixto</button>
                        </div>
                        @if($cashEnabled)
                            <select name="cash_session_id" class="form-select form-select-sm mb-2 @error('cash_session_id') is-invalid @enderror" required>
                                @foreach($sessions as $session)
                                    <option value="{{ $session->id }}" @selected((string) old('cash_session_id') === (string) $session->id)>Caja: {{ $session->caja?->name }} (abierta {{ $session->opened_at->format('d/m H:i') }})</option>
                                @endforeach
                            </select>
                        @elseif($branches->count() > 1)
                            <select name="branch_id" class="form-select form-select-sm mb-2">
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <div id="payments"></div>
                        <div class="d-flex justify-content-between small mt-2"><span>Pagado</span><span id="paid">0</span></div>
                        <div class="d-flex justify-content-between small text-success fw-semibold d-none" id="change-row"><span>Vuelto</span><span id="change">0</span></div>
                        <div class="d-none" id="credit-row">
                            <div class="d-flex justify-content-between small text-danger fw-semibold"><span>Saldo pendiente (crédito)</span><span id="balance">0</span></div>
                            <div class="d-flex align-items-center gap-2 mt-1 small">
                                <label for="due-date" class="text-muted text-nowrap">Vence el</label>
                                <input type="date" name="due_date" id="due-date" class="form-control form-control-sm" min="{{ now()->format('Y-m-d') }}" value="{{ old('due_date') }}">
                            </div>
                        </div>
                        <input type="text" name="notes" class="form-control form-control-sm mt-2" placeholder="Observaciones" maxlength="1000" value="{{ old('notes') }}">
                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3" id="submit" disabled>
                            <i class="bi bi-check2-circle"></i> Registrar venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div id="hidden-fields"></div>
    </form>
</div>

<template id="payment-template">
    <div class="input-group input-group-sm mb-1 payment-row">
        <select class="form-select payment-method" style="max-width: 45%">
            @foreach($paymentMethods as $method)
                <option value="{{ $method->id }}" data-reference="{{ $method->requires_reference ? 1 : 0 }}" data-cash="{{ $method->isCash() ? 1 : 0 }}">{{ $method->name }}</option>
            @endforeach
        </select>
        <input type="number" class="form-control payment-amount text-end" min="0" step="0.01" placeholder="Monto">
        <input type="text" class="form-control payment-reference d-none" placeholder="Referencia" maxlength="80">
        <button type="button" class="btn btn-outline-danger payment-remove" title="Quitar"><i class="bi bi-x"></i></button>
    </div>
</template>

<script>
(function () {
    const symbol = @json($symbol);
    const decimals = @json($decimals);
    const money = (n) => symbol + ' ' + Number(n || 0).toFixed(decimals);
    const round = (n) => Math.round((Number(n) || 0) * 100) / 100;
    const urls = {
        products: @json($withInventory ? route('products.search') : null),
        pets: @json(route('clients.pets', ['client' => '__ID__'])),
        billable: @json(route('pos.billable', ['pet' => '__ID__'])),
    };
    const json = (url) => fetch(url, { headers: { 'Accept': 'application/json' } }).then((r) => r.ok ? r.json() : []);
    const $ = (id) => document.getElementById(id);

    // Tras un error de validación se recupera el carrito enviado.
    let cart = (@json(old('items', []))).map((l) => Object.assign({}, l, {
        quantity: Number(l.quantity) || 1, unit_price: Number(l.unit_price) || 0, discount: Number(l.discount) || 0,
        product_id: l.product_id ? Number(l.product_id) : undefined, service_id: l.service_id ? Number(l.service_id) : undefined,
        consultation_id: l.consultation_id ? Number(l.consultation_id) : undefined,
        lab_request_id: l.lab_request_id ? Number(l.lab_request_id) : undefined,
        hospitalization_id: l.hospitalization_id ? Number(l.hospitalization_id) : undefined,
    }));
    // Una línea puede venir de algo ya atendido; su clave coincide con la de Billables.
    const LINKS = { consultation_id: 'consulta', lab_request_id: 'estudio', hospitalization_id: 'internación' };
    const linkField = (l) => Object.keys(LINKS).find((field) => l[field]);
    const linkKey = (l) => { const field = linkField(l); return field ? field + ':' + l[field] : null; };
    const added = new Set(cart.map(linkKey).filter(Boolean));

    // ---- Carrito ----
    function add(line) {
        // Un mismo producto o servicio de mostrador suma cantidad en vez de repetirse.
        const same = cart.find((l) => !linkKey(l) && !linkKey(line) &&
            ((line.product_id && l.product_id === line.product_id) || (line.service_id && l.service_id === line.service_id && l.type === 'service')));
        if (same) { same.quantity = round(same.quantity + (line.quantity || 1)); }
        else { cart.push(Object.assign({ quantity: 1, discount: 0 }, line)); }
        render();
    }

    function render() {
        const body = $('cart');
        body.innerHTML = '';
        cart.forEach(function (line, index) {
            const total = Math.max(round(line.quantity * line.unit_price - line.discount), 0);
            const short = line.stock !== undefined && !linkKey(line) && line.quantity > line.stock;
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="ps-3 small"><div class="desc"></div>' + (short ? '<div class="text-danger">Stock: ' + line.stock + '</div>' : '') +
                (linkField(line) ? '<span class="badge bg-info-subtle text-info-emphasis">' + LINKS[linkField(line)] + '</span>' : '') + '</td>' +
                '<td><input type="number" class="form-control form-control-sm px-1" min="0.01" step="0.01" data-field="quantity"></td>' +
                '<td><input type="number" class="form-control form-control-sm px-1" min="0" step="0.01" data-field="unit_price"></td>' +
                '<td><input type="number" class="form-control form-control-sm px-1" min="0" step="0.01" data-field="discount"></td>' +
                '<td class="text-end pe-2 small fw-semibold">' + money(total) + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-link text-danger p-0" title="Quitar"><i class="bi bi-x-circle"></i></button></td>';
            tr.querySelector('.desc').textContent = line.description;
            tr.querySelectorAll('input').forEach(function (input) {
                input.value = line[input.dataset.field];
                input.addEventListener('change', function () { line[input.dataset.field] = Math.max(round(input.value), 0); render(); });
            });
            tr.querySelector('button').addEventListener('click', function () {
                cart.splice(index, 1);
                const key = linkKey(line);
                if (key && !cart.some((l) => linkKey(l) === key)) {
                    added.delete(key);
                    loadBillable();
                }
                render();
            });
            body.appendChild(tr);
        });
        $('cart-empty').classList.toggle('d-none', cart.length > 0);
        totals();
    }

    function totals() {
        const subtotal = round(cart.reduce((s, l) => s + Math.max(round(l.quantity * l.unit_price - l.discount), 0), 0));
        const discount = Math.min(round($('discount').value), subtotal);
        const total = round(subtotal - discount);
        const paid = round([...document.querySelectorAll('.payment-amount')].reduce((s, i) => s + (Number(i.value) || 0), 0));
        $('subtotal').textContent = money(subtotal);
        $('total').textContent = money(total);
        $('paid').textContent = money(paid);
        $('change').textContent = money(paid - total);
        $('balance').textContent = money(total - paid);
        $('change-row').classList.toggle('d-none', paid <= total);
        $('credit-row').classList.toggle('d-none', paid >= total || cart.length === 0);
        $('submit').disabled = cart.length === 0;
        return total;
    }

    // ---- Pagos ----
    function addPayment(amount) {
        const row = $('payment-template').content.firstElementChild.cloneNode(true);
        const method = row.querySelector('.payment-method');
        const reference = row.querySelector('.payment-reference');
        const toggleReference = () => reference.classList.toggle('d-none', method.selectedOptions[0].dataset.reference !== '1');
        method.addEventListener('change', toggleReference);
        row.querySelector('.payment-amount').value = amount ?? '';
        row.querySelector('.payment-amount').addEventListener('input', totals);
        row.querySelector('.payment-remove').addEventListener('click', function () { row.remove(); totals(); });
        $('payments').appendChild(row);
        toggleReference();
        totals();
    }
    $('add-payment').addEventListener('click', () => addPayment());
    $('discount').addEventListener('input', totals);
    // El primer pago propone el total al vuelo, mientras nadie lo haya tocado.
    addPayment();
    const firstAmount = document.querySelector('.payment-amount');
    let firstTouched = false;
    firstAmount.addEventListener('input', () => firstTouched = true);
    const renderOriginal = render;
    render = function () { renderOriginal(); if (!firstTouched) { firstAmount.value = totals() || ''; totals(); } };

    // ---- Productos ----
    if (urls.products) {
        let timer = null;
        $('product-search').addEventListener('input', function () {
            clearTimeout(timer);
            const term = this.value.trim();
            if (term.length < 2) { $('product-results').innerHTML = ''; return; }
            timer = setTimeout(async () => {
                const items = await json(urls.products + '?q=' + encodeURIComponent(term));
                // Lector de código de barras: coincidencia exacta se agrega sola.
                const exact = items.find((p) => p.barcode === term || p.code === term);
                if (exact) { pick(exact); this.value = ''; $('product-results').innerHTML = ''; return; }
                $('product-results').innerHTML = items.length ? '' : '<div class="list-group-item text-muted small">Sin resultados</div>';
                items.forEach(function (p) {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'list-group-item list-group-item-action d-flex justify-content-between' + (p.stock <= 0 ? ' text-muted' : '');
                    b.innerHTML = '<span></span><small></small>';
                    b.querySelector('span').textContent = p.label;
                    b.querySelector('small').textContent = 'Stock ' + p.stock + ' · ' + money(p.sale_price);
                    b.addEventListener('click', () => pick(p));
                    $('product-results').appendChild(b);
                });
            }, 250);
        });
    }
    function pick(p) {
        add({ type: 'product', product_id: p.id, description: p.label, unit_price: Number(p.sale_price) || 0, stock: p.stock });
    }

    // ---- Servicios y otros ----
    document.querySelectorAll('[data-service]').forEach(function (button) {
        button.addEventListener('click', function () {
            const s = JSON.parse(button.dataset.service);
            add({ type: 'service', service_id: s.id, description: s.name, unit_price: s.price });
        });
    });
    $('service-filter').addEventListener('input', function () {
        const term = this.value.toLowerCase();
        document.querySelectorAll('[data-service]').forEach((b) => b.classList.toggle('d-none', !b.textContent.toLowerCase().includes(term)));
    });
    $('other-add').addEventListener('click', function () {
        const description = $('other-description').value.trim();
        if (!description) return $('other-description').focus();
        add({ type: 'other', description, unit_price: Math.max(round($('other-price').value), 0) });
        $('other-description').value = ''; $('other-price').value = '';
    });

    // ---- Cliente, mascota y consultas por cobrar ----
    const petSelect = $('pos-pet');
    async function loadPets(clientId) {
        petSelect.innerHTML = '<option value="">—</option>';
        petSelect.disabled = !clientId;
        if (!clientId) return;
        const pets = await json(urls.pets.replace('__ID__', clientId));
        pets.forEach((p) => petSelect.add(new Option(p.name, p.id)));
        const preselect = @json($selectedPetId);
        if (preselect) petSelect.value = preselect;
        if (pets.length === 1 && !petSelect.value) petSelect.value = pets[0].id;
        loadBillable();
    }
    async function loadBillable() {
        const list = $('billable-list');
        list.innerHTML = '';
        $('billable').classList.add('d-none');
        if (!petSelect.value) return;
        const pending = (await json(urls.billable.replace('__ID__', petSelect.value))).filter((c) => !added.has(c.key));
        pending.forEach(function (c) {
            const total = c.lines.reduce((s, l) => s + l.quantity * l.unit_price, 0);
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center border rounded px-2 py-1 small';
            row.innerHTML = '<span></span><button type="button" class="btn btn-sm btn-outline-primary">Agregar ' + money(total) + '</button>';
            row.querySelector('span').textContent = c.label + ' (' + c.lines.length + ' ítems)';
            row.querySelector('button').addEventListener('click', function () {
                added.add(c.key);
                c.lines.forEach((l) => cart.push(Object.assign({ discount: 0 }, l)));
                render();
                row.remove();
                if (!list.children.length) $('billable').classList.add('d-none');
            });
            list.appendChild(row);
        });
        $('billable').classList.toggle('d-none', pending.length === 0);
    }
    petSelect.addEventListener('change', loadBillable);
    document.addEventListener('pos:selected', (e) => loadPets(e.detail.id));
    document.addEventListener('pos:cleared', () => loadPets(null));
    if ($('pos-id').value) loadPets($('pos-id').value);

    // ---- Envío: el carrito y los pagos viajan como campos del formulario ----
    $('pos-form').addEventListener('submit', function () {
        const hidden = $('hidden-fields');
        hidden.innerHTML = '';
        const field = (name, value) => {
            if (value === undefined || value === null || value === '') return;
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = name; input.value = value;
            hidden.appendChild(input);
        };
        cart.forEach(function (l, i) {
            ['type', 'product_id', 'service_id', 'consultation_id', 'lab_request_id', 'hospitalization_id', 'description', 'quantity', 'unit_price', 'discount']
                .forEach((key) => field('items[' + i + '][' + key + ']', l[key]));
        });
        document.querySelectorAll('.payment-row').forEach(function (row, i) {
            const amount = Number(row.querySelector('.payment-amount').value) || 0;
            if (amount <= 0) return;
            field('payments[' + i + '][payment_method_id]', row.querySelector('.payment-method').value);
            field('payments[' + i + '][amount]', amount);
            field('payments[' + i + '][reference]', row.querySelector('.payment-reference').value);
        });
        $('submit').disabled = true;
    });

    render();
})();
</script>
@endsection
