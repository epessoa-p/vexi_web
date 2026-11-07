{{--
    Lista ordenada de mayor a menor: la forma más rápida de leer "qué pesa más".
    Un solo color, porque el largo de la barra ya dice cuánto.
    Parámetros: title, sub, rows, format ('money'|'count'), unit (texto tras la cantidad), limit, empty, url
--}}
@php
    $format = $format ?? 'money';
    $limit = $limit ?? 6;
    $rows = collect($rows);
    $render = function ($row) use ($format, $money, $num, &$unit) {
        return $format === 'money' ? $money($row['value']) : $num($row['value']);
    };
@endphp
<div class="rpt-card">
    <div class="rpt-head rpt-head-row">
        <div>
            <h3 class="rpt-card-title">{{ $title }}</h3>
            @if(! empty($sub))<p class="rpt-card-sub">{{ $sub }}</p>@endif
        </div>
        @if(! empty($url))<a href="{{ $url }}" class="small text-decoration-none d-print-none">Ver detalle</a>@endif
    </div>

    @if($rows->isEmpty())
        @include('admin.reports.partials.empty', ['text' => $empty ?? 'Sin datos en este período.'])
    @else
        @foreach([$rows->take($limit), $rows->slice($limit)] as $group => $slice)
            @if($slice->isNotEmpty())
                @if($group === 1)
                    <details class="rpt-more"><summary>Ver {{ $slice->count() === 1 ? 'el restante' : 'los ' . $slice->count() . ' restantes' }}</summary>
                @endif
                <ol class="rpt-rank">
                    @foreach($slice as $row)
                        <li>
                            <div class="rpt-rank-row">
                                <span class="rpt-rank-label">
                                    {{ $row['label'] }}
                                    @if(isset($row['quantity']) && $format === 'money')<small>· {{ $num($row['quantity']) }} {{ $quantityUnit ?? 'unid.' }}</small>@endif
                                </span>
                                <span class="rpt-rank-value">{{ $render($row) }}@if(! empty($showShare))<small>{{ round($row['share']) }} %</small>@endif</span>
                            </div>
                            <div class="rpt-rank-bar" style="width: {{ number_format($row['width'], 2, '.', '') }}%"></div>
                        </li>
                    @endforeach
                </ol>
                @if($group === 1)</details>@endif
            @endif
        @endforeach
    @endif
</div>
