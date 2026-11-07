@extends('layouts.app')

@section('title', 'Reportes · ' . $sections[$section]['label'])

@php
    $money = fn ($value) => \App\Support\Money::format($value, $tenantCompany);
    $num = fn ($value) => \App\Support\Stock::number($value);
    $q = $period->query();
    $link = fn (array $overrides) => route('reports.index', ['section' => $section] + $period->query($overrides));
@endphp

@push('styles')
    @include('admin.reports.partials.styles')
@endpush

@section('page')
<div class="container-fluid rpt-root" style="max-width: 1320px;">
    {{-- Encabezado solo para el papel: la pantalla ya lo dice con sus controles. --}}
    <div class="rpt-print-head">
        <strong>{{ $tenantCompany?->name }}</strong> · Reporte de {{ mb_strtolower($sections[$section]['label']) }}<br>
        {{ $period->label() }}@if($period->previousRangeLabel()) · comparado con {{ $period->previousRangeLabel() }}@endif
    </div>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3 rpt-noprint">
        <div>
            <h1 class="mb-1">Reportes</h1>
            <p class="text-muted mb-0">{{ $sections[$section]['question'] }}</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()" title="Elige «Guardar como PDF» en el diálogo de impresión">
                <i class="bi bi-printer"></i> Imprimir o PDF
            </button>
            @if($canExport)
                <a class="btn btn-outline-success" href="{{ route('reports.export', ['section' => $section] + $q) }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            @endif
        </div>
    </div>

    <nav class="rpt-tabs rpt-noprint mb-3" aria-label="Secciones de reportes">
        @foreach($sections as $slug => $meta)
            <a href="{{ route('reports.index', ['section' => $slug] + $q) }}" class="rpt-tab {{ $slug === $section ? 'is-active' : '' }}" @if($slug === $section) aria-current="page" @endif>
                <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
            </a>
        @endforeach
    </nav>

    {{-- Un solo filtro para todo lo que hay debajo. --}}
    <form method="GET" action="{{ route('reports.index', ['section' => $section]) }}" class="rpt-filters rpt-noprint mb-4">
        <div class="rpt-presets" role="group" aria-label="Período">
            @foreach($presets as $value => $label)
                <a href="{{ $link(['preset' => $value, 'from' => null, 'to' => null, 'group' => null]) }}" class="rpt-pill {{ $period->preset === $value ? 'is-active' : '' }}" @if($period->preset === $value) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            <details class="rpt-custom" {{ $period->preset === 'custom' ? 'open' : '' }}>
                <summary class="rpt-pill {{ $period->preset === 'custom' ? 'is-active' : '' }}">Elegir fechas</summary>
                <div class="rpt-custom-body">
                    <input type="hidden" name="preset" value="custom">
                    <label class="small text-muted" for="rpt-from">Desde</label>
                    <input type="date" id="rpt-from" name="from" class="form-control form-control-sm" value="{{ $period->from->toDateString() }}" required>
                    <label class="small text-muted" for="rpt-to">Hasta</label>
                    <input type="date" id="rpt-to" name="to" class="form-control form-control-sm" value="{{ $period->to->toDateString() }}" required>
                    @if($period->branchId)<input type="hidden" name="branch_id" value="{{ $period->branchId }}">@endif
                    <button type="submit" class="btn btn-sm btn-primary">Ver</button>
                </div>
            </details>
        </div>
        <div class="rpt-filter-side">
            @if(count($period->groupOptions()) > 1 && $section !== 'inventario')
                <div class="rpt-segmented" role="group" aria-label="Agrupar por">
                    <span class="small text-muted me-1">Ver por</span>
                    @foreach($period->groupOptions() as $value => $label)
                        <a href="{{ $link(['group' => $value]) }}" class="{{ $period->group === $value ? 'is-active' : '' }}" @if($period->group === $value) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            @endif
            @if($branches->count() > 1)
                <select class="form-select form-select-sm rpt-branch" aria-label="Sucursal" onchange="window.location.href = this.value">
                    <option value="{{ $link(['branch_id' => null]) }}">Todas las sucursales</option>
                    @foreach($branches as $branch)
                        <option value="{{ $link(['branch_id' => $branch->id]) }}" @selected($period->branchId === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </form>

    <p class="rpt-range rpt-noprint">
        <i class="bi bi-calendar3"></i> <strong>{{ $period->label() }}</strong>
        @if($period->previousRangeLabel() && $section !== 'inventario')
            <span class="text-muted">· los porcentajes comparan con {{ $period->previousRangeLabel() }}</span>
        @endif
    </p>

    @include('admin.reports.sections.' . $section)
</div>
@endsection

@push('scripts')
    @include('admin.reports.partials.chart-script')
@endpush
