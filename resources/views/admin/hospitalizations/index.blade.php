@extends('layouts.app')

@section('title', 'Hospitalización')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $canEdit = $can('hospitalizations.edit');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-hospital"></i> Hospitalización</h1>
            <p class="text-muted mb-0">{{ $activeCount }} {{ $activeCount === 1 ? 'paciente internado' : 'pacientes internados' }}.</p>
        </div>
        @if($can('hospitalizations.create'))
            <a href="{{ route('hospitalizations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Ingresar paciente</a>
        @endif
    </div>

    <ul class="nav nav-pills mb-3 flex-wrap">
        @foreach(\App\Models\Hospitalization::STATUSES as $value => [$label])
            <li class="nav-item"><a class="nav-link {{ $status === $value ? 'active' : '' }}" href="{{ route('hospitalizations.index', ['status' => $value]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <div class="row g-4">
        <div class="{{ $status === 'active' ? 'col-xl-8' : 'col-12' }}">
            <form method="GET" class="mb-3 d-flex gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar paciente o propietario…">
                <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th class="ps-3">Paciente</th><th>Motivo</th><th>Espacio</th><th>Responsable</th><th>Tiempo</th><th>Estado</th></tr></thead>
                            <tbody>
                                @forelse($hospitalizations as $h)
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('hospitalizations.show', $h) }}" class="fw-semibold text-decoration-none">{{ $h->pet?->name }}</a>
                                            <div class="small text-muted">{{ $h->pet?->species?->name }} · {{ $h->pet?->client?->full_name }}</div>
                                        </td>
                                        <td class="small">{{ $h->reason }}</td>
                                        <td class="small">{{ $h->kennel?->name ?? '—' }}</td>
                                        <td class="small">{{ $h->veterinarian?->name ?? '—' }}</td>
                                        <td class="small text-nowrap">
                                            {{ $h->stayDays() }} {{ $h->stayDays() === 1 ? 'día' : 'días' }}
                                            <div class="text-muted">desde {{ $h->admitted_at->format('d/m H:i') }}</div>
                                        </td>
                                        <td>
                                            @if($h->isActive())
                                                <span class="badge bg-{{ $h->condition_color }}">{{ $h->condition_label }}</span>
                                            @else
                                                <span class="badge bg-{{ $h->status_color }}">{{ $h->status_label }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-5">No hay internaciones en este estado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-center">{{ $hospitalizations->links() }}</div>
        </div>

        @if($status === 'active')
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-alarm"></i> Tomas atrasadas y próximas 12 horas</h6>
                        @forelse($doses as $dose)
                            @php $stay = $dose->treatment->hospitalization; @endphp
                            <div class="border-top py-2 small d-flex justify-content-between align-items-center gap-2">
                                <div>
                                    <span class="fw-semibold {{ $dose->isOverdue() ? 'text-danger' : '' }}">{{ $dose->scheduled_at->format('d/m H:i') }}</span>
                                    @if($dose->isOverdue())<span class="badge bg-danger">Atrasada</span>@endif
                                    <div><a href="{{ route('hospitalizations.show', $stay) }}" class="text-decoration-none">{{ $stay->pet?->name }}</a>{{ $stay->kennel ? ' · ' . $stay->kennel->name : '' }}</div>
                                    <div class="text-muted">{{ $dose->treatment->medication }} · {{ $dose->treatment->dose }}</div>
                                </div>
                                @if($canEdit)
                                    <form action="{{ route('hospitalizations.doses.give', $dose) }}" method="POST" class="m-0">
                                        @csrf
                                        <button class="btn btn-sm btn-success text-nowrap" type="submit"><i class="bi bi-check2"></i> Aplicar</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="small text-muted mb-0">No hay tomas pendientes en las próximas horas.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-grid-3x3-gap"></i> Espacios</h6>
                            @if($can('kennels.view'))
                                <a href="{{ route('kennels.index') }}" class="small text-decoration-none">Administrar</a>
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($kennels as $kennel)
                                <span class="badge {{ $occupied->has($kennel->id) ? 'bg-danger' : 'bg-success' }}">{{ $kennel->name }}</span>
                            @empty
                                <span class="small text-muted">Sin espacios registrados.</span>
                            @endforelse
                        </div>
                        @if($kennels->isNotEmpty())<div class="small text-muted mt-2">Verde: libre · Rojo: ocupado</div>@endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
