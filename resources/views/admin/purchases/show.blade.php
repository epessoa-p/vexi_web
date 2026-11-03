@extends('layouts.app')

@section('title', "Compra {$purchase->code}")

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
    $num = fn ($v) => \App\Support\Stock::number($v);
    $canEdit = $can('purchases.edit') && ! $purchase->isCancelled();
    $pendingItems = $purchase->items->filter(fn ($i) => $i->pendingQuantity() > 0);
@endphp
<div class="container-fluid" style="max-width: 1300px;">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Compra {{ $purchase->code }} <span class="badge bg-{{ $purchase->status_color }} fs-6 align-middle">{{ $purchase->status_label }}</span></h1>
            <div class="text-muted">
                <a href="{{ route('suppliers.show', $purchase->supplier_id) }}" class="text-decoration-none">{{ $purchase->supplier?->name }}</a>
                · {{ $purchase->purchased_at->format('d/m/Y') }}
                @if($purchase->document_number) · Factura {{ $purchase->document_number }} @endif
                · recibe en {{ $purchase->branch?->name }} · {{ $purchase->user?->name }}
            </div>
        </div>
        <div class="d-flex gap-2">
            @if($purchase->invoice_url)
                <a href="{{ $purchase->invoice_url }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-paperclip"></i> Factura</a>
            @endif
            <a href="{{ route('purchases.index') }}" class="btn btn-outline-secondary">Compras</a>
        </div>
    </div>

    @if($purchase->isCancelled())
        <div class="alert alert-dark"><i class="bi bi-x-octagon"></i> Anulada el {{ $purchase->cancelled_at->format('d/m/Y H:i') }}: {{ $purchase->cancel_reason }}</div>
    @endif
    @if($purchase->closing_notes)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Cerrada con diferencias de recepción: {{ $purchase->closing_notes }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Producto</th><th class="text-end">Pedido</th><th class="text-end">Recibido</th><th class="text-end">Costo</th><th class="text-end">Desc.</th><th class="text-end pe-3">Total</th></tr></thead>
                        <tbody>
                            @foreach($purchase->items as $item)
                                @php $diff = round((float) $item->quantity - (float) $item->received_quantity, 2); @endphp
                                <tr>
                                    <td class="ps-3"><a href="{{ route('products.show', $item->product_id) }}" class="text-decoration-none">{{ $item->product?->label }}</a></td>
                                    <td class="text-end">{{ $num($item->quantity) }}</td>
                                    <td class="text-end {{ $diff > 0 ? 'text-warning fw-semibold' : 'text-success' }}">
                                        {{ $num($item->received_quantity) }}
                                        @if($diff > 0 && $purchase->status !== 'ordered')<div class="small">faltan {{ $num($diff) }}</div>@endif
                                    </td>
                                    <td class="text-end">{{ $money($item->unit_cost) }}</td>
                                    <td class="text-end text-muted">{{ (float) $item->discount ? $money($item->discount) : '—' }}</td>
                                    <td class="text-end pe-3 fw-semibold">{{ $money($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end pe-3">{{ $money($purchase->subtotal) }}</td></tr>
                            @if((float) $purchase->discount)<tr><td colspan="5" class="text-end">Descuento</td><td class="text-end pe-3">− {{ $money($purchase->discount) }}</td></tr>@endif
                            @if((float) $purchase->tax)<tr><td colspan="5" class="text-end">Impuesto ({{ $num($purchase->tax_rate) }} %)</td><td class="text-end pe-3">{{ $money($purchase->tax) }}</td></tr>@endif
                            <tr class="fs-5"><td colspan="5" class="text-end fw-bold">Total</td><td class="text-end pe-3 fw-bold">{{ $money($purchase->total) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Recepción de lo pendiente --}}
            @if($canEdit && $purchase->isPending() && $pendingItems->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3 border-start border-success border-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-box-arrow-in-down"></i> Registrar recepción</h6>
                        <form action="{{ route('purchases.receive', $purchase) }}" method="POST">
                            @csrf
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Producto</th><th class="text-end">Pendiente</th><th style="width: 7rem">Recibido</th><th>Lote</th><th>Vencimiento</th></tr></thead>
                                <tbody>
                                    @foreach($pendingItems as $item)
                                        <tr>
                                            <td>{{ $item->product?->label }}</td>
                                            <td class="text-end">{{ $num($item->pendingQuantity()) }}</td>
                                            <td><input type="number" name="items[{{ $item->id }}][quantity]" class="form-control form-control-sm" min="0" max="{{ $item->pendingQuantity() }}" step="0.01" value="{{ $item->pendingQuantity() }}"></td>
                                            @if($item->product?->track_batches)
                                                <td><input type="text" name="items[{{ $item->id }}][lot]" class="form-control form-control-sm" maxlength="60" placeholder="Lote"></td>
                                                <td><input type="date" name="items[{{ $item->id }}][expires_at]" class="form-control form-control-sm"></td>
                                            @else
                                                <td colspan="2" class="small text-muted">Sin control de lote</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="d-flex gap-2">
                                <input type="text" name="notes" class="form-control form-control-sm" placeholder="Observaciones (ej. caja golpeada, faltante)" maxlength="255">
                                <button class="btn btn-success btn-sm text-nowrap" type="submit"><i class="bi bi-check2"></i> Registrar recepción</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if($purchase->receptions->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Recepciones</h6>
                        @foreach($purchase->receptions as $reception)
                            <div class="border-bottom py-2 small">
                                <div class="d-flex justify-content-between"><span class="fw-semibold">{{ $reception->received_at->format('d/m/Y H:i') }}</span><span class="text-muted">{{ $reception->user?->name }}</span></div>
                                <div>{{ $reception->items->map(fn ($ri) => $num($ri->quantity) . ' × ' . $ri->purchaseItem?->product?->name . ($ri->lot ? " (lote {$ri->lot}" . ($ri->expires_at ? ', vence ' . $ri->expires_at->format('d/m/Y') : '') . ')' : ''))->implode(' · ') }}</div>
                                @if($reception->notes)<div class="text-muted">{{ $reception->notes }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack"></i> Pagos al proveedor</h6>
                    <dl class="row small mb-2">
                        <dt class="col-6 text-muted fw-normal">Total</dt><dd class="col-6 text-end">{{ $money($purchase->total) }}</dd>
                        <dt class="col-6 text-muted fw-normal">Pagado</dt><dd class="col-6 text-end">{{ $money($purchase->paid) }}</dd>
                        <dt class="col-6 fw-semibold">Saldo</dt><dd class="col-6 text-end fw-semibold {{ $purchase->balance > 0 ? 'text-danger' : '' }}">{{ $money($purchase->balance) }}</dd>
                        @if($purchase->due_date)
                            <dt class="col-6 text-muted fw-normal">Vence</dt><dd class="col-6 text-end {{ $purchase->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $purchase->due_date->format('d/m/Y') }}</dd>
                        @endif
                    </dl>
                    @foreach($purchase->payments as $payment)
                        <div class="d-flex justify-content-between border-top py-1 small">
                            <span>{{ $payment->paid_at->format('d/m/Y') }} · {{ $payment->paymentMethod?->name }}@if($payment->reference)<div class="text-muted">Ref. {{ $payment->reference }}</div>@endif</span>
                            <span class="fw-semibold">{{ $money($payment->amount) }}</span>
                        </div>
                    @endforeach

                    @if($canEdit && $purchase->balance > 0)
                        <form action="{{ route('purchases.payments.store', $purchase) }}" method="POST" class="border-top pt-3 mt-2 d-flex flex-column gap-2">
                            @csrf
                            <div class="fw-semibold small">Registrar pago</div>
                            <input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0.01" max="{{ $purchase->balance }}" value="{{ $purchase->balance }}" required>
                            <select name="payment_method_id" class="form-select form-select-sm">
                                @foreach($paymentMethods as $method)
                                    <option value="{{ $method->id }}">{{ $method->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="reference" class="form-control form-control-sm" placeholder="Referencia (si aplica)" maxlength="80">
                            @if($sessions->isNotEmpty())
                                <select name="cash_session_id" class="form-select form-select-sm">
                                    <option value="">No sale de caja (banco u otro)</option>
                                    @foreach($sessions as $session)
                                        <option value="{{ $session->id }}">Sale de {{ $session->caja?->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-plus-lg"></i> Registrar pago</button>
                        </form>
                    @endif
                </div>
            </div>

            @if($purchase->notes)
                <div class="card border-0 shadow-sm mb-3"><div class="card-body small"><span class="text-muted">Observaciones:</span> {{ $purchase->notes }}</div></div>
            @endif

            @if($canEdit && $purchase->status === 'partial')
                <details class="card border-0 shadow-sm mb-3">
                    <summary class="card-body fw-semibold text-warning-emphasis"><i class="bi bi-flag"></i> Cerrar con diferencias</summary>
                    <form action="{{ route('purchases.close', $purchase) }}" method="POST" class="card-body pt-0 d-flex flex-column gap-2"
                          onsubmit="return confirm('Lo que no llegó ya no se esperará y el saldo con el proveedor se ajustará a lo recibido. ¿Continuar?')">
                        @csrf
                        <input type="text" name="closing_notes" class="form-control form-control-sm" placeholder="Motivo (ej. proveedor sin stock)" maxlength="255" required>
                        <button class="btn btn-sm btn-warning" type="submit">Cerrar compra</button>
                    </form>
                </details>
            @endif

            @if($canEdit && $purchase->status === 'ordered' && $purchase->payments->isEmpty())
                <details class="card border-0 shadow-sm">
                    <summary class="card-body text-danger fw-semibold"><i class="bi bi-x-octagon"></i> Anular compra</summary>
                    <form action="{{ route('purchases.cancel', $purchase) }}" method="POST" class="card-body pt-0 d-flex flex-column gap-2" onsubmit="return confirm('¿Anular la compra {{ $purchase->code }}?')">
                        @csrf
                        <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Motivo (obligatorio)" maxlength="255" required>
                        <button class="btn btn-sm btn-danger" type="submit">Anular</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</div>
@endsection
