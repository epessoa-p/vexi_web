@extends('layouts.app')

@section('title', 'Sala de espera')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $canEdit = $can('appointments.edit');
    // Con el módulo de consultas, "Atender" abre la consulta clínica.
    $consultasOn = $tenantCompany?->planAllows('consultas') && $can('consultations.create');
    $openWalkIn = $errors->any() && old('client_id') !== null;
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-hourglass-split"></i> Sala de espera</h1>
            <p class="text-muted mb-0">{{ ucfirst(now()->translatedFormat('l j \d\e F')) }} · se actualiza sola cada minuto.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-calendar-week"></i> Agenda</a>
            @if($can('appointments.create'))
                <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#walk-in" aria-expanded="{{ $openWalkIn ? 'true' : 'false' }}">
                    <i class="bi bi-person-plus"></i> Llegó sin cita
                </button>
            @endif
        </div>
    </div>

    @if($can('appointments.create'))
        <div class="collapse {{ $openWalkIn ? 'show' : '' }}" id="walk-in">
            <form action="{{ route('waiting-room.walk-in') }}" method="POST" class="card border-0 shadow-sm mb-4">
                @csrf
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Registrar llegada sin cita</h6>
                    @include('admin.appointments.partials.patient-fields', ['selectedClient' => null, 'selectedPetId' => null, 'prefix' => 'walkin'])
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label">Servicio</label>
                            <select name="service_id" class="form-select">
                                <option value="">Sin especificar</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" @selected((string) old('service_id') === (string) $service->id)>{{ $service->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Profesional</label>
                            <select name="veterinarian_id" class="form-select">
                                <option value="">Asignar después</option>
                                @foreach($professionals as $professional)
                                    <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id') === (string) $professional->id)>{{ $professional->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Motivo</label>
                            <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" maxlength="255">
                        </div>
                        <div class="col-12"><button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-right"></i> Pasar a espera</button></div>
                    </div>
                </div>
            </form>
        </div>
    @endif

    <div class="row g-4">
        {{-- En espera --}}
        <div class="col-xl-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-warning-subtle border-0 fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-hourglass-split"></i> En espera</span><span class="badge bg-warning text-dark">{{ $waiting->count() }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($waiting as $i => $appointment)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-dark me-1">{{ $i + 1 }}</span>
                                    <a href="{{ route('appointments.show', $appointment) }}" class="fw-semibold text-decoration-none">{{ $appointment->pet?->name }}</a>
                                    @if($appointment->pet?->hasClinicalWarnings())<i class="bi bi-exclamation-triangle-fill text-danger" title="Alertas clínicas"></i>@endif
                                    <div class="small text-muted">{{ $appointment->client?->full_name }} · {{ $appointment->service?->name ?? 'Sin servicio' }}</div>
                                    @if($appointment->reason)<div class="small">{{ $appointment->reason }}</div>@endif
                                </div>
                                @php $minutes = $appointment->waiting_minutes; @endphp
                                <span class="badge {{ $minutes >= 30 ? 'bg-danger' : ($minutes >= 15 ? 'bg-warning text-dark' : 'bg-light text-dark border') }}" title="Tiempo de espera">
                                    {{ $minutes }} min
                                </span>
                            </div>
                            @if($canEdit)
                                <div class="d-flex gap-2 mt-2">
                                    <form action="{{ route('waiting-room.assign', $appointment) }}" method="POST" class="flex-grow-1 m-0">
                                        @csrf
                                        @method('PATCH')
                                        <select name="veterinarian_id" class="form-select form-select-sm" onchange="this.form.submit()" title="Profesional">
                                            <option value="">Sin asignar</option>
                                            @foreach($professionals as $professional)
                                                <option value="{{ $professional->id }}" @selected($appointment->veterinarian_id === $professional->id)>{{ $professional->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <form action="{{ $consultasOn ? route('consultations.start-from-appointment', $appointment) : route('appointments.transition', [$appointment, 'start']) }}" method="POST" class="m-0">
                                        @csrf
                                        <button class="btn btn-sm btn-info text-nowrap" type="submit"><i class="bi bi-play-fill"></i> Atender</button>
                                    </form>
                                </div>
                            @else
                                <div class="small text-muted mt-1">Atiende: {{ $appointment->veterinarian?->name ?? 'sin asignar' }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted py-4">Nadie esperando.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- En atención --}}
        <div class="col-xl-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info-subtle border-0 fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-play-circle"></i> En atención</span><span class="badge bg-info text-dark">{{ $inProgress->count() }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($inProgress as $appointment)
                        <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <a href="{{ route('appointments.show', $appointment) }}" class="fw-semibold text-decoration-none">{{ $appointment->pet?->name }}</a>
                                <div class="small text-muted">{{ $appointment->veterinarian?->name ?? 'Sin asignar' }} · desde {{ $appointment->started_at?->format('H:i') }}</div>
                            </div>
                            @if($appointment->consultation && $can('consultations.view'))
                                {{-- La cita se cierra al finalizar su consulta. --}}
                                <a href="{{ route($appointment->consultation->isOpen() && $can('consultations.edit') ? 'consultations.edit' : 'consultations.show', $appointment->consultation) }}" class="btn btn-sm btn-outline-info text-nowrap">
                                    <i class="bi bi-clipboard2-pulse"></i> Consulta
                                </a>
                            @elseif($consultasOn)
                                <form action="{{ route('consultations.start-from-appointment', $appointment) }}" method="POST" class="m-0">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-info text-nowrap" type="submit"><i class="bi bi-clipboard2-pulse"></i> Abrir consulta</button>
                                </form>
                            @elseif($canEdit)
                                <form action="{{ route('appointments.transition', [$appointment, 'finish']) }}" method="POST" class="m-0">
                                    @csrf
                                    <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check-lg"></i> Finalizar</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted py-4">Nadie en atención.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Próximas de hoy --}}
        <div class="col-xl-4 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary-subtle border-0 fw-semibold d-flex justify-content-between">
                    <span><i class="bi bi-calendar-check"></i> Próximas de hoy</span><span class="badge bg-primary">{{ $upcoming->count() }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($upcoming as $appointment)
                        <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <span class="fw-semibold {{ $appointment->starts_at->isPast() ? 'text-danger' : '' }}">{{ $appointment->starts_at->format('H:i') }}</span>
                                <a href="{{ route('appointments.show', $appointment) }}" class="text-decoration-none ms-1">{{ $appointment->pet?->name }}</a>
                                <span class="badge bg-{{ $appointment->status_color }} ms-1">{{ $appointment->status_label }}</span>
                                <div class="small text-muted">{{ $appointment->client?->full_name }}{{ $appointment->veterinarian ? ' · ' . $appointment->veterinarian->name : '' }}</div>
                            </div>
                            @if($canEdit)
                                <form action="{{ route('appointments.transition', [$appointment, 'check_in']) }}" method="POST" class="m-0">
                                    @csrf
                                    <button class="btn btn-sm btn-warning text-nowrap" type="submit"><i class="bi bi-door-open"></i> Llegó</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-center text-muted py-4">No hay más citas hoy.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @if($done->isNotEmpty())
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 fw-semibold">Cerradas hoy ({{ $done->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @foreach($done as $appointment)
                            <tr>
                                <td class="ps-3">{{ $appointment->starts_at->format('H:i') }}</td>
                                <td><a href="{{ route('appointments.show', $appointment) }}" class="text-decoration-none">{{ $appointment->pet?->name }}</a></td>
                                <td class="text-muted small">{{ $appointment->client?->full_name }}</td>
                                <td class="text-muted small">{{ $appointment->veterinarian?->name }}</td>
                                <td class="text-end pe-3"><span class="badge bg-{{ $appointment->status_color }}">{{ $appointment->status_label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<script>
    // Refresco automático, salvo mientras se está registrando una llegada.
    setInterval(function () {
        const walkIn = document.getElementById('walk-in');
        const busy = (walkIn && walkIn.classList.contains('show')) || ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName);
        if (!busy) window.location.reload();
    }, 60000);
</script>
@endsection
