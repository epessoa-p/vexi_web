{{-- Variación frente al período anterior: nunca inventa un porcentaje sin base. --}}
@if(! empty($delta))
    @php
        $icon = ['up' => 'bi-arrow-up-right', 'down' => 'bi-arrow-down-right', 'flat' => 'bi-dash'][$delta['direction']];
    @endphp
    @if($delta['pct'] === null)
        <span class="rpt-delta is-neutral"><small>Sin datos para comparar</small></span>
    @else
        <span class="rpt-delta is-{{ $delta['tone'] }}" title="Comparado con {{ $period->previousLabel }}">
            <i class="bi {{ $icon }}" aria-hidden="true"></i>
            {{ $delta['pct'] > 0 ? '+' : '' }}{{ number_format($delta['pct'], 0) }} %
            <small class="d-print-none">vs. {{ $period->previousLabel }}</small>
        </span>
    @endif
@endif
