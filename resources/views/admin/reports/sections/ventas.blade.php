@php
    $d = $data['deltas'];
    $range = ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()];
@endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-4">
        @include('admin.reports.partials.tile', ['label' => 'Total vendido', 'value' => $money($data['total']), 'delta' => $d['total'], 'hint' => 'Sin anuladas ni devoluciones', 'url' => route('sales.index', $range)])
    </div>
    <div class="col-6 col-xl-4">
        @include('admin.reports.partials.tile', ['label' => 'Cantidad de ventas', 'value' => $num($data['count']), 'delta' => $d['count'], 'url' => route('sales.index', $range)])
    </div>
    <div class="col-12 col-xl-4">
        @include('admin.reports.partials.tile', ['label' => 'Ticket promedio', 'value' => $money($data['ticket']), 'delta' => $d['ticket'], 'hint' => 'Lo que deja cada venta, en promedio'])
    </div>
</div>

<div class="mb-3">
    @include('admin.reports.partials.chart', ['title' => 'Ventas por período', 'sub' => 'Incluye lo vendido a crédito, aunque todavía no se haya cobrado.', 'chart' => $data['chart'], 'empty' => 'Sin ventas en este período.'])
</div>

<div class="mb-3">
    @include('admin.reports.partials.segments', ['title' => '¿De dónde vienen las ventas?', 'parts' => $data['byType'], 'empty' => 'Sin ventas en este período.'])
</div>

<h2 class="rpt-heading">Lo que más se vende</h2>
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        @include('admin.reports.partials.ranked', ['title' => 'Productos', 'rows' => $data['products'], 'limit' => 8, 'empty' => 'Sin productos vendidos en este período.'])
    </div>
    <div class="col-lg-6">
        @include('admin.reports.partials.ranked', ['title' => 'Servicios', 'rows' => $data['services'], 'limit' => 8, 'quantityUnit' => 'veces', 'empty' => 'Sin servicios vendidos en este período.'])
    </div>
</div>

<h2 class="rpt-heading">Cómo se reparte</h2>
<div class="row g-3">
    <div class="{{ $data['branches']->count() > 1 ? 'col-lg-4' : 'col-lg-6' }}">
        @include('admin.reports.partials.ranked', ['title' => 'Por categoría de producto', 'rows' => $data['categories'], 'showShare' => true, 'empty' => 'Sin productos vendidos en este período.'])
    </div>
    <div class="{{ $data['branches']->count() > 1 ? 'col-lg-4' : 'col-lg-6' }}">
        @include('admin.reports.partials.ranked', ['title' => 'Por usuario', 'sub' => 'Quién registró la venta.', 'rows' => $data['users'], 'showShare' => true, 'empty' => 'Sin ventas en este período.'])
    </div>
    @if($data['branches']->count() > 1)
        <div class="col-lg-4">
            @include('admin.reports.partials.ranked', ['title' => 'Por sucursal', 'rows' => $data['branches'], 'showShare' => true])
        </div>
    @endif
</div>
