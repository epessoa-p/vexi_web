{{--
    Barra segmentada: cómo se reparte un todo. El color sigue al concepto, no al monto.
    Parámetros: title, sub, parts [{label, value, color, share}], empty
--}}
@php
    $parts = collect($parts);
    $inkDark = fn (string $color) => strtolower($color) === '#eda100';
@endphp
<div class="rpt-card">
    <div class="rpt-head">
        <h3 class="rpt-card-title">{{ $title }}</h3>
        @if(! empty($sub))<p class="rpt-card-sub">{{ $sub }}</p>@endif
    </div>
    @if($parts->isEmpty())
        @include('admin.reports.partials.empty', ['text' => $empty ?? 'Sin datos en este período.'])
    @else
        <div class="rpt-segments" role="img" aria-label="{{ $title }}: {{ $parts->map(fn ($p) => $p['label'] . ' ' . round($p['share']) . ' %')->implode(', ') }}">
            @foreach($parts as $part)
                <div class="rpt-segment {{ $inkDark($part['color']) ? 'is-dark' : '' }}" style="flex: {{ number_format($part['share'], 2, '.', '') }} 1 0; background: {{ $part['color'] }}" title="{{ $part['label'] }}: {{ $money($part['value']) }} ({{ round($part['share']) }} %)">{{ $part['share'] >= 12 ? round($part['share']) . ' %' : '' }}</div>
            @endforeach
        </div>
        <ul class="rpt-legend">
            @foreach($parts as $part)
                <li><span class="rpt-key" style="background: {{ $part['color'] }}"></span>{{ $part['label'] }} <strong>{{ $money($part['value']) }}</strong></li>
            @endforeach
        </ul>
    @endif
</div>
