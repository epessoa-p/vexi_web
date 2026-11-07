@php
    $withValue = $data['withValue'];
    $low = collect($data['low']);
    $expiring = collect($data['expiring']);
    $col = $withValue ? 'col-6 col-xl-3' : 'col-6 col-xl-4';
    $canProducts = auth()->user()->hasPermissionInCompany('products.view', $tenantCompany);
    $canInventory = auth()->user()->hasPermissionInCompany('inventory.view', $tenantCompany);
@endphp

<p class="small text-muted"><i class="bi bi-info-circle"></i> Esta pantalla muestra <strong>cómo está el inventario hoy</strong>. Las fechas elegidas solo afectan a los movimientos.</p>

@if($data['negative'] > 0)
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>{{ $data['negative'] === 1 ? 'Un producto tiene' : $data['negative'] . ' productos tienen' }} existencia negativa: se administró más de lo registrado. Conviene hacer un ajuste de inventario.</div>
    </div>
@endif

<div class="row g-3 mb-3">
    @if($withValue)
        <div class="{{ $col }}">
            @include('admin.reports.partials.tile', ['label' => 'Valor del inventario', 'value' => $money($data['cost']), 'hint' => 'Lo que te costó lo que hay en estantería'])
        </div>
        <div class="{{ $col }}">
            @include('admin.reports.partials.tile', ['label' => 'Si lo vendes todo', 'value' => $money($data['sale']), 'hint' => 'Al precio de venta actual'])
        </div>
    @endif
    <div class="{{ $col }}">
        @include('admin.reports.partials.tile', ['label' => 'Bajo el mínimo', 'value' => $num($data['lowCount']), 'hint' => $data['lowCount'] ? 'Productos por reponer' : 'Todo con stock suficiente',
            'url' => $canProducts ? route('products.index', ['status' => 'low']) : null])
    </div>
    <div class="{{ $col }}">
        @include('admin.reports.partials.tile', ['label' => 'Lotes vencidos', 'value' => $num($data['expiredCount']), 'hint' => $num($data['expiringCount']) . ' por vencer en ' . $data['expiryDays'] . ' días',
            'url' => $canInventory ? route('expiry.index', $data['expiredCount'] ? ['tab' => 'expired'] : []) : null])
    </div>
</div>

@if($withValue)
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            @include('admin.reports.partials.ranked', ['title' => '¿Dónde está tu dinero?', 'sub' => 'Valor al costo por categoría.', 'rows' => $data['byCategory'], 'showShare' => true, 'limit' => 8, 'empty' => 'Sin existencias valorizadas.'])
        </div>
        <div class="col-lg-6">
            @include('admin.reports.partials.ranked', ['title' => 'Productos con más valor', 'sub' => 'Los que más capital inmovilizan.', 'rows' => $data['byProduct'], 'limit' => 8, 'quantityUnit' => 'en stock', 'empty' => 'Sin existencias valorizadas.'])
        </div>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="rpt-card">
            <div class="rpt-head rpt-head-row">
                <div>
                    <h3 class="rpt-card-title">Por reponer</h3>
                    <p class="rpt-card-sub">Existencia total frente al mínimo definido.</p>
                </div>
                @if($canProducts)<a href="{{ route('products.index', ['status' => 'low']) }}" class="small text-decoration-none d-print-none">Ver todos</a>@endif
            </div>
            @if($low->isEmpty())
                @include('admin.reports.partials.empty', ['icon' => 'bi-check2-circle', 'text' => 'Nada por reponer.'])
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 rpt-table">
                        <thead><tr><th>Producto</th><th class="text-end">Hay</th><th class="text-end">Mínimo</th></tr></thead>
                        <tbody>
                            @foreach($low->take(8) as $row)
                                <tr><td>{{ $row['label'] }}</td><td class="text-end fw-semibold">{{ $num($row['stock']) }}</td><td class="text-end text-muted">{{ $num($row['min']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rpt-card">
            <div class="rpt-head rpt-head-row">
                <div>
                    <h3 class="rpt-card-title">Vencimientos</h3>
                    <p class="rpt-card-sub">Lo vencido y lo que vence en {{ $data['expiryDays'] }} días, lo más urgente primero.</p>
                </div>
                @if($canInventory)<a href="{{ route('expiry.index') }}" class="small text-decoration-none d-print-none">Ver todos</a>@endif
            </div>
            @if($expiring->isEmpty())
                @include('admin.reports.partials.empty', ['icon' => 'bi-check2-circle', 'text' => 'Nada vencido ni por vencer.'])
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 rpt-table">
                        <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th class="text-end">Hay</th></tr></thead>
                        <tbody>
                            @foreach($expiring->take(8) as $batch)
                                @php $expired = $batch->expires_at->lt(now()->startOfDay()); @endphp
                                <tr>
                                    <td>{{ $batch->product?->label }}</td>
                                    <td class="text-muted">{{ $batch->lot }}</td>
                                    <td class="text-nowrap">{{ $batch->expires_at->format('d/m/Y') }}@if($expired) <span class="badge bg-danger">Vencido</span>@endif</td>
                                    <td class="text-end">{{ $num($batch->quantity) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="rpt-card" style="height: auto;">
    <div class="rpt-head rpt-head-row">
        <div>
            <h3 class="rpt-card-title">Movimientos del período</h3>
            <p class="rpt-card-sub">Unidades que entraron y salieron, por motivo. {{ $period->label() }}.</p>
        </div>
        @if($canInventory)<a href="{{ route('stock-movements.index') }}" class="small text-decoration-none d-print-none">Ver kardex</a>@endif
    </div>
    @if(collect($data['movements'])->isEmpty())
        @include('admin.reports.partials.empty', ['text' => 'Sin movimientos en este período.'])
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 rpt-table">
                <thead><tr><th>Motivo</th><th class="text-end">Entradas</th><th class="text-end">Salidas</th></tr></thead>
                <tbody>
                    @foreach($data['movements'] as $row)
                        <tr><td>{{ $row['label'] }}</td><td class="text-end">{{ $row['in'] ? $num($row['in']) : '—' }}</td><td class="text-end">{{ $row['out'] ? $num($row['out']) : '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
