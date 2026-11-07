@php
    $d = $data['deltas'];
    $h = $data['hospital'];
    $withVaccines = $data['withVaccines'];
    $withHospital = $data['withHospital'];
    $tiles = 2 + (int) $withVaccines + (int) $withHospital;
    $col = $tiles === 4 ? 'col-6 col-xl-3' : ($tiles === 3 ? 'col-6 col-xl-4' : 'col-6');
@endphp

@if($period->branchId && ($withVaccines || $withHospital))
    <p class="small text-muted"><i class="bi bi-info-circle"></i> Las vacunas y las internaciones no se registran por sucursal: esas cifras son de toda la veterinaria.</p>
@endif

<div class="row g-3 mb-3">
    <div class="{{ $col }}">
        @include('admin.reports.partials.tile', ['label' => 'Consultas', 'value' => $num($data['count']), 'delta' => $d['count'], 'hint' => 'Finalizadas en el período', 'url' => route('consultations.index')])
    </div>
    <div class="{{ $col }}">
        @include('admin.reports.partials.tile', ['label' => 'Mascotas atendidas', 'value' => $num($data['patients']), 'delta' => $d['patients'], 'hint' => 'Cada mascota cuenta una vez'])
    </div>
    @if($withVaccines)
        <div class="{{ $col }}">
            @include('admin.reports.partials.tile', ['label' => 'Vacunas aplicadas', 'value' => $num($data['vaccines']), 'delta' => $d['vaccines'], 'url' => route('health.index')])
        </div>
    @endif
    @if($withHospital)
        <div class="{{ $col }}">
            @include('admin.reports.partials.tile', ['label' => 'Internaciones', 'value' => $num($h['admitted']), 'hint' => $h['active'] === 1 ? 'Un paciente internado hoy' : $h['active'] . ' pacientes internados hoy', 'url' => route('hospitalizations.index')])
        </div>
    @endif
</div>

<div class="mb-3">
    @include('admin.reports.partials.chart', ['title' => 'Consultas por período', 'chart' => $data['chart'], 'empty' => 'Sin consultas finalizadas en este período.'])
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        @include('admin.reports.partials.ranked', ['title' => 'Diagnósticos frecuentes', 'sub' => 'Diagnóstico principal de cada consulta.', 'rows' => $data['diagnoses'], 'format' => 'count', 'limit' => 8, 'empty' => 'Sin diagnósticos registrados en este período.'])
    </div>
    <div class="col-lg-6">
        @include('admin.reports.partials.ranked', ['title' => 'Consultas por veterinario', 'rows' => $data['veterinarians'], 'format' => 'count', 'limit' => 8, 'empty' => 'Sin consultas en este período.'])
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        @include('admin.reports.partials.ranked', ['title' => 'Pacientes por especie', 'sub' => 'Mascotas atendidas.', 'rows' => $data['species'], 'format' => 'count', 'empty' => 'Sin pacientes en este período.'])
    </div>
    @if($withVaccines)
        <div class="col-lg-4">
            @include('admin.reports.partials.ranked', ['title' => 'Vacunas aplicadas', 'sub' => 'Dosis por vacuna.', 'rows' => $data['vaccinesByType'], 'format' => 'count', 'empty' => 'Sin vacunas aplicadas en este período.'])
        </div>
    @endif
    @if($withHospital)
        <div class="col-lg-4">
            <div class="rpt-card">
                <div class="rpt-head">
                    <h3 class="rpt-card-title">Hospitalizaciones</h3>
                    <p class="rpt-card-sub">Ingresos del período y cómo terminaron.</p>
                </div>
                @if($h['admitted'] === 0)
                    @include('admin.reports.partials.empty', ['text' => 'Sin internaciones en este período.'])
                @else
                    <dl class="rpt-sum border-0 pt-0 mt-0">
                        <div><dt>Ingresaron</dt><dd>{{ $h['admitted'] }}</dd></div>
                        <div><dt>Alta médica</dt><dd>{{ $h['discharged'] }}</dd></div>
                        <div><dt>Fallecieron</dt><dd>{{ $h['deceased'] }}</dd></div>
                        <div><dt>Siguen internados</dt><dd>{{ $h['admitted'] - $h['discharged'] - $h['deceased'] }}</dd></div>
                        @if($h['averageDays'] !== null)<div class="is-total"><dt>Estadía promedio</dt><dd>{{ $h['averageDays'] }} {{ $h['averageDays'] == 1 ? 'día' : 'días' }}</dd></div>@endif
                    </dl>
                @endif
            </div>
        </div>
    @endif
</div>
