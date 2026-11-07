@php
    $f = $data['finance'];
    $t = $f['totals'];
    $s = $data['sales'];
    $c = $data['clinic'];
    $range = ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()];
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="rpt-card">
            <div class="rpt-hero-label">Utilidad estimada</div>
            <div class="rpt-hero {{ $t['profit'] < 0 ? 'is-negative' : '' }}">{{ $money($t['profit']) }}</div>
            @include('admin.reports.partials.delta', ['delta' => $f['deltas']['profit']])
            <dl class="rpt-sum">
                <div><dt>Ingresos</dt><dd>{{ $money($t['income']) }}</dd></div>
                <div><dt>− Costo de productos vendidos</dt><dd>{{ $money($t['cost']) }}</dd></div>
                @if($f['withExpenses'])<div><dt>− Gastos</dt><dd>{{ $money($t['expenses']) }}</dd></div>@endif
                <div class="is-total"><dt>= Te queda</dt><dd>{{ $money($t['profit']) }}</dd></div>
            </dl>
            @include('admin.reports.partials.how-profit')
        </div>
    </div>
    <div class="col-lg-7">
        <div class="rpt-card">
            <div class="rpt-head"><h3 class="rpt-card-title">Lo más importante</h3></div>
            <ul class="rpt-insights">
                @foreach($data['insights'] as $insight)
                    <li><i class="bi {{ $insight['icon'] }}" aria-hidden="true"></i><span>{!! $insight['html'] !!}</span></li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<div class="mb-3">
    @include('admin.reports.partials.chart', [
        'title' => $f['withExpenses'] ? '¿Entra más de lo que sale?' : 'Ingresos en el tiempo',
        'sub' => $f['withExpenses'] ? 'Dinero cobrado frente a gastos registrados.' : 'Dinero cobrado, menos reembolsos.',
        'chart' => $f['chart'],
    ])
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        @include('admin.reports.partials.tile', ['label' => 'Ventas', 'value' => $num($s['count']), 'delta' => $s['deltas']['count'],
            'hint' => 'Ticket promedio: ' . $money($s['ticket']), 'url' => route('sales.index', $range)])
    </div>
    @if($c)
        <div class="col-6 col-lg-3">
            @include('admin.reports.partials.tile', ['label' => 'Consultas', 'value' => $num($c['count']), 'delta' => $c['deltas']['count'],
                'hint' => $c['patients'] === 1 ? 'Un paciente atendido' : $c['patients'] . ' pacientes atendidos', 'url' => route('reports.index', ['section' => 'clinica'] + $period->query())])
        </div>
    @endif
    <div class="col-6 col-lg-3">
        @include('admin.reports.partials.tile', ['label' => 'Te deben (hoy)', 'value' => $money($f['receivable']),
            'hint' => $f['receivableOverdue'] > 0 ? $money($f['receivableOverdue']) . ' ya vencido' : 'Nada vencido', 'url' => route('receivables.index')])
    </div>
    @if($f['withPayables'])
        <div class="col-6 col-lg-3">
            @include('admin.reports.partials.tile', ['label' => 'Debes (hoy)', 'value' => $money($f['payable']), 'hint' => 'A proveedores', 'url' => route('payables.index')])
        </div>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-4">
        @include('admin.reports.partials.ranked', ['title' => 'Servicios más vendidos', 'rows' => $s['services'], 'limit' => 5, 'quantityUnit' => 'veces',
            'empty' => 'Sin servicios vendidos en este período.', 'url' => route('reports.index', ['section' => 'ventas'] + $period->query())])
    </div>
    <div class="col-lg-4">
        @include('admin.reports.partials.ranked', ['title' => 'Productos más vendidos', 'rows' => $s['products'], 'limit' => 5,
            'empty' => 'Sin productos vendidos en este período.', 'url' => route('reports.index', ['section' => 'ventas'] + $period->query())])
    </div>
    <div class="col-lg-4">
        <div class="rpt-card">
            <div class="rpt-head">
                <h3 class="rpt-card-title">Para revisar hoy</h3>
                <p class="rpt-card-sub">No depende de las fechas elegidas.</p>
            </div>
            @if($data['attention'] === [])
                @include('admin.reports.partials.empty', ['icon' => 'bi-check2-circle', 'text' => 'Todo en orden: nada urgente por ahora.'])
            @else
                <ul class="rpt-attn">
                    @foreach($data['attention'] as $item)
                        <li>
                            @if($item['url'])
                                <a href="{{ $item['url'] }}"><i class="bi {{ $item['icon'] }} is-{{ $item['tone'] }}" aria-hidden="true"></i><span>{{ $item['text'] }}</span><i class="bi bi-chevron-right d-print-none" aria-hidden="true"></i></a>
                            @else
                                <div class="rpt-attn-row"><i class="bi {{ $item['icon'] }} is-{{ $item['tone'] }}" aria-hidden="true"></i><span>{{ $item['text'] }}</span></div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
