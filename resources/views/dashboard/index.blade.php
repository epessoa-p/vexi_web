@extends('layouts.app')

@section('title', 'Dashboard')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold mb-1">Dashboard</h4>
            <p class="text-muted mb-0">Resumen general del sistema</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalUsers }}</div>
                        <div class="kpi-label">Usuarios</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-people"></i> Registrados</div>
                    </div>
                    <div class="kpi-icon" style="background: #7c4dff;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalPersonal }}</div>
                        <div class="kpi-label">Personal</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-person-vcard"></i> Empleados</div>
                    </div>
                    <div class="kpi-icon" style="background: #ff6d00;">
                        <i class="bi bi-person-badge"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalBranches }}</div>
                        <div class="kpi-label">Sucursales</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-diagram-2"></i> Activas</div>
                    </div>
                    <div class="kpi-icon" style="background: #00c853;">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalCajas }}</div>
                        <div class="kpi-label">Cajas</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-safe"></i> Registradas</div>
                    </div>
                    <div class="kpi-icon" style="background: #0288d1;">
                        <i class="bi bi-safe2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($finance)
        @php $money = fn ($v) => \App\Support\Money::format($v, $company); @endphp
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-graph-up"></i> Indicadores económicos <small class="text-muted fw-normal">({{ now()->translatedFormat('F') }})</small></h6>
                    @if(auth()->user()->hasPermissionInCompany('sales.create', $company))
                        <a href="{{ route('pos.create') }}" class="btn btn-sm btn-success"><i class="bi bi-cart-plus"></i> Nueva venta</a>
                    @endif
                </div>
                <div class="row g-2 text-center">
                    @foreach(array_filter([
                        ['Ingresos de hoy', $money($finance['today']), $finance['sales_today'] . ' ventas', 'text-success'],
                        ['Ingresos del mes', $money($finance['month']), $finance['sales_month'] . ' ventas', 'text-success'],
                        $finance['expenses_month'] !== null ? ['Gastos del mes', $money($finance['expenses_month']), null, 'text-danger'] : null,
                        $finance['expenses_month'] !== null ? ['Utilidad estimada', $money($finance['profit_month']), 'ingresos − costo − gastos', $finance['profit_month'] < 0 ? 'text-danger' : 'text-primary'] : null,
                        ['Cuentas por cobrar', $money($finance['receivable']), null, $finance['receivable'] > 0 ? 'text-warning' : 'text-muted'],
                        $finance['payable'] !== null ? ['Cuentas por pagar', $money($finance['payable']), 'a proveedores', $finance['payable'] > 0 ? 'text-danger' : 'text-muted'] : null,
                    ]) as [$label, $value, $hint, $color])
                        <div class="col">
                            <div class="border rounded py-2 h-100">
                                <div class="fs-5 fw-bold {{ $color }}">{{ $value }}</div>
                                <div class="small text-muted">{{ $label }}</div>
                                @if($hint)<div class="small text-muted" style="font-size: .75rem">{{ $hint }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($today)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-calendar-week"></i> Agenda de hoy</h6>
                    <div class="d-flex gap-2">
                        <a href="{{ route('waiting-room.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-hourglass-split"></i> Sala de espera</a>
                        <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-outline-primary">Ver agenda</a>
                    </div>
                </div>
                <div class="row g-2 text-center mb-3">
                    @foreach([
                        ['total', 'Citas', 'text-dark'],
                        ['pending', 'Sin confirmar', 'text-secondary'],
                        ['waiting', 'En espera', 'text-warning'],
                        ['in_progress', 'En atención', 'text-info'],
                        ['attended', 'Atendidas', 'text-success'],
                    ] as [$key, $label, $color])
                        <div class="col">
                            <div class="border rounded py-2">
                                <div class="fs-4 fw-bold {{ $color }}">{{ $today[$key] }}</div>
                                <div class="small text-muted">{{ $label }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @forelse($today['next'] as $appointment)
                    <a href="{{ route('appointments.show', $appointment) }}" class="d-flex justify-content-between py-2 border-bottom text-decoration-none text-reset small">
                        <span><strong>{{ $appointment->starts_at->format('H:i') }}</strong> · {{ $appointment->pet?->name }}</span>
                        <span class="text-muted">{{ $appointment->veterinarian?->name ?? 'Sin asignar' }}</span>
                    </a>
                @empty
                    <p class="small text-muted mb-0">No hay más citas pendientes hoy.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if($hospital)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-hospital"></i> Hospitalización</h6>
                    <a href="{{ route('hospitalizations.index') }}" class="btn btn-sm btn-outline-primary">Ver internados</a>
                </div>
                <div class="row g-2 text-center">
                    @foreach([
                        ['active', 'Internados', 'text-primary'],
                        ['critical', 'En estado crítico', 'text-danger'],
                        ['overdue', 'Tomas atrasadas', 'text-danger'],
                        ['next', 'Tomas en 6 horas', 'text-warning'],
                    ] as [$key, $label, $color])
                        <div class="col-6 col-md-3">
                            <div class="border rounded py-2">
                                <div class="fs-4 fw-bold {{ $hospital[$key] ? $color : 'text-muted' }}">{{ $hospital[$key] }}</div>
                                <div class="small text-muted">{{ $label }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($health)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-shield-plus"></i> Alertas sanitarias</h6>
                    <a href="{{ route('health.index') }}" class="btn btn-sm btn-outline-primary">Ver calendario</a>
                </div>
                <div class="row g-2 text-center">
                    @foreach([
                        ['vaccines_overdue', 'Vacunas atrasadas', 'text-danger', ['tab' => 'overdue', 'kind' => 'vaccines']],
                        ['vaccines_upcoming', 'Vacunas próximas', 'text-warning', ['tab' => 'upcoming', 'kind' => 'vaccines']],
                        ['dewormings_overdue', 'Desparasitaciones atrasadas', 'text-danger', ['tab' => 'overdue', 'kind' => 'dewormings']],
                        ['dewormings_upcoming', 'Desparasitaciones próximas', 'text-warning', ['tab' => 'upcoming', 'kind' => 'dewormings']],
                    ] as [$key, $label, $color, $params])
                        <div class="col-6 col-md-3">
                            <a href="{{ route('health.index', $params) }}" class="d-block border rounded py-2 text-decoration-none text-reset">
                                <div class="fs-4 fw-bold {{ $health[$key] ? $color : 'text-muted' }}">{{ $health[$key] }}</div>
                                <div class="small text-muted">{{ $label }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($inventory)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-box-seam"></i> Alertas de inventario</h6>
                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-primary">Ver productos</a>
                </div>
                <div class="row g-2 text-center">
                    @foreach([
                        ['low', 'Stock mínimo', 'text-warning', route('products.index', ['status' => 'low'])],
                        ['out', 'Agotados', 'text-danger', route('products.index', ['status' => 'out'])],
                        ['expiring', 'Lotes por vencer', 'text-warning', route('expiry.index')],
                        ['expired', 'Lotes vencidos', 'text-danger', route('expiry.index', ['tab' => 'expired'])],
                    ] as [$key, $label, $color, $url])
                        <div class="col-6 col-md-3">
                            <a href="{{ $url }}" class="d-block border rounded py-2 text-decoration-none text-reset">
                                <div class="fs-4 fw-bold {{ $inventory[$key] ? $color : 'text-muted' }}">{{ $inventory[$key] }}</div>
                                <div class="small text-muted">{{ $label }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-heart-pulse text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 fw-semibold">Bienvenido a Vexi</h5>
            <p class="text-muted mb-0">
                Ya están disponibles clientes y mascotas, agenda, sala de espera, consultas con receta, vacunas y desparasitaciones, y farmacia e inventario.
                Reportes y notificaciones llegan en las siguientes fases.
            </p>
        </div>
    </div>
</div>
@endsection
