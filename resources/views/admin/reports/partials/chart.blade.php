{{--
    Gráfico de tiempo. Dos series comparten una sola escala. Cada gráfico trae sus
    datos en tabla: la lectura nunca depende del puntero ni del color.
    Parámetros: title, sub, chart, empty
--}}
@php
    $format = $chart['format'] === 'money' ? $money : fn ($v) => $num($v);
    $hasData = collect($chart['series'])->flatMap(fn ($s) => $s['values'])->contains(fn ($v) => (float) $v !== 0.0);
    $summary = collect($chart['series'])->map(fn ($s) => $s['name'] . ': ' . $format(array_sum($s['values'])))->implode(', ');
@endphp
<div class="rpt-card">
    <div class="rpt-head">
        <h3 class="rpt-card-title">{{ $title }}</h3>
        @if(! empty($sub))<p class="rpt-card-sub">{{ $sub }}</p>@endif
        @if(count($chart['series']) > 1 && $hasData)
            <ul class="rpt-legend">
                @foreach($chart['series'] as $serie)
                    <li><span class="rpt-key-line" style="background: {{ $serie['color'] }}"></span>{{ $serie['name'] }} <strong>{{ $format(array_sum($serie['values'])) }}</strong></li>
                @endforeach
            </ul>
        @endif
    </div>

    @if($hasData)
        <div class="rpt-chart" data-chart='@json($chart)' tabindex="0" role="img" aria-label="{{ $title }}. {{ $summary }}. Usa las flechas para recorrer los valores.">
            <div class="rpt-tip" aria-hidden="true"></div>
        </div>
        <details class="rpt-table-toggle">
            <summary>Ver los datos en tabla</summary>
            <div class="table-responsive" style="max-height: 20rem;">
                <table class="table table-sm rpt-table">
                    <thead><tr><th>Período</th>@foreach($chart['series'] as $serie)<th class="text-end">{{ $serie['name'] }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($chart['labels'] as $i => $label)
                            <tr><td>{{ $label }}</td>@foreach($chart['series'] as $serie)<td class="text-end">{{ $format($serie['values'][$i]) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @else
        @include('admin.reports.partials.empty', ['icon' => 'bi-graph-up', 'text' => $empty ?? 'Todavía no hay movimientos en este período. Prueba con un rango más amplio.'])
    @endif
</div>
