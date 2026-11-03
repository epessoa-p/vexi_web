@php
    $money = fn ($v) => \App\Support\Money::format($v, $company);
    $num = fn ($v) => \App\Support\Stock::number($v);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante {{ $sale->code }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; margin: 0; background: #eee; }
        .ticket { width: 72mm; margin: 12px auto; background: #fff; padding: 4mm; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .item { margin-bottom: 4px; }
        .muted { color: #444; }
        .big { font-size: 15px; }
        .stamp { border: 2px solid #000; padding: 4px; text-align: center; font-weight: bold; margin: 6px 0; }
        .toolbar { text-align: center; margin: 12px; font-family: sans-serif; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 6px; background: #1f2937; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .ticket { margin: 0; width: auto; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Imprimir</button></div>
    <div class="ticket">
        <div class="center bold big">{{ $company?->name }}</div>
        <div class="center muted">
            @if($company?->tax_id){{ $company->tax_id_label }} {{ $company->tax_id }}<br>@endif
            {{ $sale->branch?->name ?? $company?->address }}
            @if($company?->phone)<br>Tel. {{ $company->phone }}@endif
        </div>
        <hr>
        <div class="row"><span class="bold">{{ $sale->code }}</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
        <div>Cliente: {{ $sale->client?->full_name ?? 'Consumidor final' }}</div>
        @if($sale->client?->document_number)<div>{{ $sale->client->document_type }}: {{ $sale->client->document_number }}</div>@endif
        @if($sale->pet)<div>Mascota: {{ $sale->pet->name }}</div>@endif
        <div class="muted">Atendió: {{ $sale->user?->name }}</div>
        @if($sale->isCancelled())<div class="stamp">ANULADA</div>@endif
        <hr>
        @foreach($sale->items as $item)
            <div class="item">
                <div>{{ $item->description }}</div>
                <div class="row">
                    <span>{{ $num($item->quantity) }} × {{ $money($item->unit_price) }}{{ (float) $item->discount ? ' − ' . $money($item->discount) : '' }}</span>
                    <span>{{ $money($item->total) }}</span>
                </div>
            </div>
        @endforeach
        <hr>
        <div class="row"><span>Subtotal</span><span>{{ $money($sale->subtotal) }}</span></div>
        @if((float) $sale->discount)<div class="row"><span>Descuento</span><span>− {{ $money($sale->discount) }}</span></div>@endif
        <div class="row bold big"><span>TOTAL</span><span>{{ $money($sale->total) }}</span></div>
        <hr>
        @foreach($sale->payments as $payment)
            <div class="row"><span>{{ $payment->paymentMethod?->name }}{{ $payment->kind === 'installment' ? ' (abono)' : '' }}</span><span>{{ $money($payment->amount) }}</span></div>
        @endforeach
        @if((float) $sale->balance > 0)
            <div class="row bold"><span>SALDO PENDIENTE</span><span>{{ $money($sale->balance) }}</span></div>
            @if($sale->due_date)<div>Vence: {{ $sale->due_date->format('d/m/Y') }}</div>@endif
        @endif
        <hr>
        <div class="center">¡Gracias por cuidar a tu mascota con nosotros!</div>
        <div class="center muted" style="font-size: 10px">Comprobante interno · no válido como factura</div>
    </div>
</body>
</html>
