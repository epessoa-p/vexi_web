@extends('layouts.app')

@section('title', 'Laboratorio')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-eyedropper"></i> Laboratorio</h1>
            <p class="text-muted mb-0">Solicitudes de análisis y estudios de imagen, y sus resultados.</p>
        </div>
        @if($can('lab_requests.create'))
            <a href="{{ route('lab.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Solicitar estudio</a>
        @endif
    </div>

    <ul class="nav nav-pills mb-3 flex-wrap">
        <li class="nav-item"><a class="nav-link {{ request('status') ? '' : 'active' }}" href="{{ route('lab.index', request()->except(['status', 'page'])) }}">Todos</a></li>
        @foreach(\App\Models\LabRequest::STATUSES as $value => [$label, $color])
            <li class="nav-item">
                <a class="nav-link {{ request('status') === $value ? 'active' : '' }}" href="{{ route('lab.index', array_merge(request()->except('page'), ['status' => $value])) }}">
                    {{ $label }} <span class="badge bg-{{ $color }}">{{ $counts[$value] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        @if(request('pet_id'))<input type="hidden" name="pet_id" value="{{ request('pet_id') }}">@endif
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Paciente o propietario</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar…">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Estudio</label>
                <select name="lab_study_type_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($studyTypes as $study)
                        <option value="{{ $study->id }}" @selected((string) request('lab_study_type_id') === (string) $study->id)>{{ $study->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-outline-primary flex-fill" type="submit" title="Filtrar"><i class="bi bi-search"></i></button>
                @if(request()->hasAny(['q', 'lab_study_type_id', 'from', 'to', 'pet_id']))
                    <a href="{{ route('lab.index') }}" class="btn btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Nº</th><th>Fecha</th><th>Estudio</th><th>Paciente</th><th>Solicitó</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($requests as $labRequest)
                            <tr>
                                <td class="ps-3"><a href="{{ route('lab.show', $labRequest) }}" class="fw-semibold text-decoration-none">{{ $labRequest->code }}</a></td>
                                <td class="small text-nowrap">{{ $labRequest->requested_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $labRequest->studyType?->name }}@if($labRequest->external_lab)<div class="small text-muted">{{ $labRequest->external_lab }}</div>@endif</td>
                                <td>
                                    {{ $labRequest->pet?->name }}
                                    <div class="small text-muted">{{ $labRequest->pet?->species?->name }} · {{ $labRequest->pet?->client?->full_name }}</div>
                                </td>
                                <td class="small">{{ $labRequest->requester?->name ?? '—' }}</td>
                                <td><span class="badge bg-{{ $labRequest->status_color }}">{{ $labRequest->status_label }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No hay estudios.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $requests->links() }}</div>
</div>
@endsection
