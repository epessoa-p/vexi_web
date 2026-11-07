@php
    $t = $data['totals'];
    $d = $data['deltas'];
    $cols = $data['withExpenses'] ? 'col-6 col-xl-3' : 'col-6 col-xl-4';
    $range = ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString()];
@endphp

<div class="row g-3 mb-3">
    <div class="{{ $cols }}">
        @include('admin.reports.partials.tile', ['label' => 'Ingresos', 'value' => $money($t['income']), 'delta' => $d['income'], 'hint' => 'Dinero cobrado, menos reembolsos'])
    </div>
    <div class="{{ $cols }}">
        @include('admin.reports.partials.tile', ['label' => 'Costo de productos', 'value' => $money($t['cost']), 'delta' => $d['cost'], 'hint' => 'De lo vendido, al precio de compra'])
    </div>
    @if($data['withExpenses'])
        <div class="{{ $cols }}">
            @include('admin.reports.partials.tile', ['label' => 'Gastos', 'value' => $money($t['expenses']), 'delta' => $d['expenses'], 'hint' => 'Registrados en el período',
                'url' => route('expenses.index', $range)])
        </div>
    @endif
    <div class="{{ $cols }}">
        @include('admin.reports.partials.tile', ['label' => 'Utilidad estimada', 'value' => $money($t['profit']), 'delta' => $d['profit'],
            'hint' => $data['withExpenses'] ? 'Ingresos − costo − gastos' : 'Ingresos − costo'])
    </div>
</div>

<div class="mb-3">
    @include('admin.reports.partials.chart', [
        'title' => $data['withExpenses'] ? 'Ingresos y gastos' : 'Ingresos',
        'sub' => 'Pasa el puntero por el gráfico para ver cada ' . mb_strtolower(\App\Support\Reports\ReportPeriod::GROUPS[$period->group]) . '.',
        'chart' => $data['chart'],
    ])
</div>

<div class="row g-3 mb-3">
    <div class="{{ $data['withExpenses'] ? 'col-lg-6' : 'col-12' }}">
        @include('admin.reports.partials.segments', ['title' => '¿Cómo te pagan?', 'sub' => 'Ingresos por método de pago.', 'parts' => $data['byMethod'],
            'empty' => 'Sin cobros en este período.'])
    </div>
    @if($data['withExpenses'])
        <div class="col-lg-6">
            @include('admin.reports.partials.ranked', ['title' => '¿En qué se va el dinero?', 'sub' => 'Gastos por categoría.', 'rows' => $data['expensesByCategory'], 'showShare' => true,
                'empty' => 'Sin gastos registrados en este período.', 'url' => route('expenses.index', $range)])
        </div>
    @endif
</div>

<h2 class="rpt-heading">Saldos de hoy</h2>
<div class="row g-3 mb-3">
    <div class="{{ $data['withPayables'] ? 'col-lg-6' : 'col-12' }}">
        @include('admin.reports.partials.ranked', [
            'title' => 'Te deben ' . $money($data['receivable']),
            'sub' => $data['receivableOverdue'] > 0 ? $money($data['receivableOverdue']) . ' ya está vencido.' : 'Nada vencido.',
            'rows' => $data['debtors'], 'empty' => 'Nadie te debe: todo cobrado.', 'url' => route('receivables.index'),
        ])
    </div>
    @if($data['withPayables'])
        <div class="col-lg-6">
            @include('admin.reports.partials.ranked', [
                'title' => 'Debes ' . $money($data['payable']), 'sub' => 'Compras pendientes de pago a proveedores.',
                'rows' => $data['creditors'], 'empty' => 'No tienes deudas con proveedores.', 'url' => route('payables.index'),
            ])
        </div>
    @endif
</div>

<div class="rpt-card" style="height: auto;">
    @include('admin.reports.partials.how-profit')
</div>
