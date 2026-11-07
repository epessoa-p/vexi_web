{{-- Indicador: etiqueta, valor, variación y una línea que lo explica. El enlace lleva al detalle. --}}
@php $tag = ! empty($url) ? 'a' : 'div'; @endphp
<{{ $tag }} @if(! empty($url)) href="{{ $url }}" @endif class="rpt-card rpt-tile">
    <span class="rpt-tile-label">
        <span>{{ $label }}</span>
        @if(! empty($url))<i class="bi bi-arrow-right-short rpt-go d-print-none" aria-hidden="true"></i>@endif
    </span>
    <div class="rpt-tile-value">{{ $value }}</div>
    @include('admin.reports.partials.delta', ['delta' => $delta ?? null])
    @if(! empty($hint))<div class="rpt-tile-hint">{{ $hint }}</div>@endif
</{{ $tag }}>
