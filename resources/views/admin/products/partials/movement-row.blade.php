{{-- Fila del kardex. Parámetros: $movement, $showProduct (bool). --}}
@php $num = fn ($value) => \App\Support\Stock::number($value); @endphp
<tr>
    <td class="ps-3 text-nowrap small">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
    @if($showProduct ?? false)
        <td>
            <a href="{{ route('products.show', $movement->product_id) }}" class="text-decoration-none">{{ $movement->product?->name }}</a>
            <div class="small text-muted">{{ $movement->product?->presentation }}</div>
        </td>
    @endif
    <td><span class="badge bg-{{ $movement->type_color }}">{{ $movement->type_label }}</span></td>
    <td class="small">
        {{ $movement->reason_label }}
        @if($movement->notes)<div class="text-muted">{{ $movement->notes }}</div>@endif
    </td>
    <td class="small">{{ $movement->branch?->name }}</td>
    <td class="small">
        @if($movement->batch)
            {{ $movement->batch->lot }}
            @if($movement->batch->expires_at)<div class="text-muted">vence {{ $movement->batch->expires_at->format('d/m/Y') }}</div>@endif
        @else
            —
        @endif
    </td>
    <td class="text-end fw-semibold {{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $num($movement->quantity) }}</td>
    <td class="text-end {{ $movement->balance_after < 0 ? 'text-danger fw-semibold' : '' }}">{{ $num($movement->balance_after) }}</td>
    <td class="small text-muted pe-3">{{ $movement->user?->name ?? 'Sistema' }}</td>
</tr>
