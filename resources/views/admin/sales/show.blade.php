@extends('layouts.app')

@section('title', "Venta {$sale->code}")

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
    $num = fn ($v) => \App\Support\Stock::number($v);
    [$label, $color] = $sale->payment_status_badge;
    $returnable = $sale->items->filter(fn ($i) => $i->returnableQuantity() > 0);
    $moneyFields = ['paymentMethods' => $paymentMethods, 'sessions' => $sessions, 'cashEnabled' => $cashEnabled];
@endphp
<div class="container-fluid" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Venta {{ $sale->code }} <span class="badge bg-{{ $color }} fs-6 align-middle">{{ $label }}</span></h1>
            <div class="text-muted">
                {{ $sale->created_at->format('d/m/Y H:i') }} · {{ $sale->user?->name }}
                @if($sale->branch) · {{ $sale->branch->name }} @endif
                · @if($sale->client)<a href="{{ route('clients.show', $sale->client) }}" class="text-decoration-none">{{ $sale->client->full_name }}</a>@else Consumidor final @endif
                @if($sale->pet) · {{ $sale->pet->name }} @endif
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="btn btn-primary" id="print-receipt"><i class="bi bi-printer"></i> Comprobante</a>
            @if($can('sales.create'))
                <a href="{{ route('pos.create') }}" class="btn btn-outline-success"><i class="bi bi-cart-plus"></i> Nueva venta</a>
            @endif
        </div>
    </div>

    @if($sale->isCancelled())
        <div class="alert alert-dark">
            <i class="bi bi-x-octagon"></i> Anulada el {{ $sale->cancelled_at->format('d/m/Y H:i') }} por {{ $sale->canceller?->name }}: {{ $sale->cancel_reason }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-0">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Ítem</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">Desc.</th><th class="text-end pe-3">Total</th></tr></thead>
                        <tbody>
                            @foreach($sale->items as $item)
                                <tr>
                                    <td class="ps-3">
                                        {{ $item->description }}
                                        <div class="small text-muted">
                                            {{ \App\Models\SaleItem::TYPES[$item->type] }}
                                            @if($item->consultation_id) · <a href="{{ route('consultations.show', $item->consultation_id) }}" class="text-decoration-none">consulta</a>@endif
                                            @if($item->lab_request_id) · <a href="{{ route('lab.show', $item->lab_request_id) }}" class="text-decoration-none">estudio</a>@endif
                                            @if($item->hospitalization_id) · <a href="{{ route('hospitalizations.show', $item->hospitalization_id) }}" class="text-decoration-none">internación</a>@endif
                                            @if((float) $item->returned_quantity > 0)<span class="text-danger"> · devuelto {{ $num($item->returned_quantity) }}</span>@endif
                                        </div>
                                    </td>
                                    <td class="text-end">{{ $num($item->quantity) }}</td>
                                    <td class="text-end">{{ $money($item->unit_price) }}</td>
                                    <td class="text-end text-muted">{{ (float) $item->discount ? $money($item->discount) : '—' }}</td>
                                    <td class="text-end pe-3 fw-semibold">{{ $money($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end pe-3">{{ $money($sale->subtotal) }}</td></tr>
                            @if((float) $sale->discount)
                                <tr><td colspan="4" class="text-end">Descuento general</td><td class="text-end pe-3">− {{ $money($sale->discount) }}</td></tr>
                            @endif
                            <tr class="fs-5"><td colspan="4" class="text-end fw-bold">Total</td><td class="text-end pe-3 fw-bold">{{ $money($sale->total) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if($sale->returns->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-arrow-counterclockwise"></i> Devoluciones</h6>
                        @foreach($sale->returns as $return)
                            <div class="border-bottom py-2 small">
                                <div class="d-flex justify-content-between">
                                    <span>{{ $return->created_at->format('d/m/Y H:i') }} · {{ $return->user?->name }}</span>
                                    <span class="fw-semibold">{{ (float) $return->amount ? 'Reembolso ' . $money($return->amount) . ' (' . $return->paymentMethod?->name . ')' : 'Descontado del saldo' }}</span>
                                </div>
                                <div class="text-muted">{{ $return->items->map(fn ($ri) => $num($ri->quantity) . ' × ' . $ri->saleItem?->description)->implode(' · ') }}{{ $return->reason ? " — {$return->reason}" : '' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(! $sale->isCancelled() && $can('sales.edit') && $returnable->isNotEmpty())
                <details class="card border-0 shadow-sm mb-3">
                    <summary class="card-body fw-bold"><i class="bi bi-arrow-counterclockwise"></i> Registrar devolución</summary>
                    <form action="{{ route('sales.return', $sale) }}" method="POST" class="card-body pt-0">
                        @csrf
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Ítem</th><th class="text-end">Se puede devolver</th><th style="width: 8rem">Devolver</th></tr></thead>
                            <tbody>
                                @foreach($returnable as $item)
                                    <tr>
                                        <td>{{ $item->description }}@if($item->affects_stock)<div class="small text-muted">vuelve al stock</div>@endif</td>
                                        <td class="text-end">{{ $num($item->returnableQuantity()) }}</td>
                                        <td><input type="number" name="quantities[{{ $item->id }}]" min="0" max="{{ $item->returnableQuantity() }}" step="0.01" class="form-control form-control-sm"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="small text-muted">El importe se descuenta primero del saldo pendiente; lo que exceda se reembolsa.</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Motivo" maxlength="255" style="max-width: 16rem">
                            @include('admin.sales.partials.money-fields', $moneyFields)
                            <button class="btn btn-sm btn-warning text-nowrap" type="submit" onclick="return confirm('¿Registrar la devolución?')">Registrar devolución</button>
                        </div>
                    </form>
                </details>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cash-stack"></i> Cobro</h6>
                    <dl class="row small mb-2">
                        <dt class="col-6 text-muted fw-normal">Total</dt><dd class="col-6 text-end">{{ $money($sale->total) }}</dd>
                        <dt class="col-6 text-muted fw-normal">Pagado</dt><dd class="col-6 text-end">{{ $money($sale->paid) }}</dd>
                        @if((float) $sale->refunded)
                            <dt class="col-6 text-muted fw-normal">Reembolsado</dt><dd class="col-6 text-end">− {{ $money($sale->refunded) }}</dd>
                        @endif
                        <dt class="col-6 fw-semibold">Saldo</dt>
                        <dd class="col-6 text-end fw-semibold {{ $sale->balance > 0 ? 'text-danger' : '' }}">{{ $money($sale->balance) }}</dd>
                        @if($sale->due_date)
                            <dt class="col-6 text-muted fw-normal">Vence</dt>
                            <dd class="col-6 text-end {{ $sale->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $sale->due_date->format('d/m/Y') }}{{ $sale->isOverdue() ? ' (vencida)' : '' }}</dd>
                        @endif
                    </dl>
                    <ul class="list-unstyled small mb-0">
                        @foreach($sale->payments as $payment)
                            <li class="d-flex justify-content-between border-top py-1">
                                <span>
                                    {{ $payment->paid_at->format('d/m H:i') }} · {{ $payment->paymentMethod?->name }}
                                    @if($payment->kind === 'installment')<span class="badge bg-light text-dark border">abono</span>@endif
                                    @if($payment->reference)<div class="text-muted">Ref. {{ $payment->reference }}</div>@endif
                                </span>
                                <span class="fw-semibold">{{ $money($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if(! $sale->isCancelled() && $sale->balance > 0 && $can('sales.create'))
                        <form action="{{ route('sales.payments.store', $sale) }}" method="POST" class="border-top pt-3 mt-2 d-flex flex-column gap-2">
                            @csrf
                            <div class="fw-semibold small">Registrar abono</div>
                            <input type="number" name="amount" class="form-control form-control-sm" step="0.01" min="0.01" max="{{ $sale->balance }}" value="{{ $sale->balance }}" required>
                            @include('admin.sales.partials.money-fields', $moneyFields + ['withReference' => true])
                            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-plus-lg"></i> Registrar abono</button>
                        </form>
                    @endif
                </div>
            </div>

            @if($sale->notes)
                <div class="card border-0 shadow-sm mb-3"><div class="card-body small"><span class="text-muted">Observaciones:</span> {{ $sale->notes }}</div></div>
            @endif

            @if(! $sale->isCancelled() && $can('sales.edit'))
                <details class="card border-0 shadow-sm border-danger">
                    <summary class="card-body text-danger fw-semibold"><i class="bi bi-x-octagon"></i> Anular venta</summary>
                    <form action="{{ route('sales.cancel', $sale) }}" method="POST" class="card-body pt-0 d-flex flex-column gap-2"
                          onsubmit="return confirm('¿Anular la venta {{ $sale->code }}? Los productos vuelven al stock y se reembolsa lo cobrado.')">
                        @csrf
                        <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Motivo (obligatorio)" maxlength="255" required>
                        @if((float) $sale->paid - (float) $sale->refunded > 0)
                            <div class="small text-muted">Se reembolsan {{ $money((float) $sale->paid - (float) $sale->refunded) }} con:</div>
                            @include('admin.sales.partials.money-fields', $moneyFields)
                        @endif
                        <button class="btn btn-sm btn-danger" type="submit">Anular venta</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</div>

@if(request('printed'))
    <script>
        // Recién vendida: abrir el comprobante para imprimir.
        window.open(@json(route('sales.receipt', $sale)), '_blank');
    </script>
@endif
@endsection
