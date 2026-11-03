@extends('layouts.app')

@section('title', 'Consultas')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-clipboard2-pulse"></i> Consultas</h1>
            <p class="text-muted mb-0">Evaluación clínica, diagnóstico, tratamiento y recetas.</p>
        </div>
        @if($can('consultations.create'))
            <a href="{{ route('consultations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva consulta</a>
        @endif
    </div>

    {{-- Pacientes que esperan consulta (vienen de la sala de espera) --}}
    @if($pending->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4 border-start border-warning border-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-hourglass-split text-warning"></i> Consultas pendientes hoy ({{ $pending->count() }})</h6>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($pending as $appointment)
                        <div class="border rounded p-2 d-flex align-items-center gap-3">
                            <div>
                                <div class="fw-semibold">{{ $appointment->pet?->name }}
                                    @if($appointment->pet?->hasClinicalWarnings())<i class="bi bi-exclamation-triangle-fill text-danger"></i>@endif
                                </div>
                                <div class="small text-muted">{{ $appointment->status_label }} · {{ $appointment->veterinarian?->name ?? 'Sin asignar' }}</div>
                            </div>
                            @if($can('consultations.create'))
                                <form action="{{ route('consultations.start-from-appointment', $appointment) }}" method="POST" class="m-0">
                                    @csrf
                                    <button class="btn btn-sm btn-info" type="submit"><i class="bi bi-play-fill"></i> Iniciar</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Paciente o propietario</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Buscar…">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todas</option>
                    @foreach(\App\Models\Consultation::STATUSES as $value => [$label])
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Profesional</label>
                <select name="veterinarian_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) request('veterinarian_id') === (string) $professional->id)>{{ $professional->name }}</option>
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
                @if(request()->hasAny(['q', 'status', 'veterinarian_id', 'from', 'to']))
                    <a href="{{ route('consultations.index') }}" class="btn btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Paciente</th>
                            <th>Motivo / diagnóstico</th>
                            <th>Profesional</th>
                            <th>Estado</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($consultations as $consultation)
                            <tr>
                                <td class="ps-3 text-nowrap">{{ $consultation->started_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $consultation->pet?->name }}</div>
                                    <small class="text-muted">{{ $consultation->pet?->species?->name }} · {{ $consultation->client?->full_name }}</small>
                                </td>
                                <td>
                                    @if($consultation->primary_diagnosis)
                                        <div>{{ $consultation->primary_diagnosis->description }}</div>
                                    @endif
                                    <small class="text-muted">{{ $consultation->reason }}</small>
                                </td>
                                <td>{{ $consultation->veterinarian?->name ?? '-' }}</td>
                                <td><span class="badge bg-{{ $consultation->status_color }}">{{ $consultation->status_label }}</span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($consultation->isOpen() && $can('consultations.edit'))
                                        <a href="{{ route('consultations.edit', $consultation) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Continuar</a>
                                    @else
                                        <a href="{{ route('consultations.show', $consultation) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay consultas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $consultations->links() }}</div>
</div>
@endsection
