@extends('layouts.app')

@section('title', 'Cita de ' . $appointment->pet?->name)

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $pet = $appointment->pet;
    $actions = $can('appointments.edit') ? $appointment->availableTransitions() : [];
    $whatsapp = $appointment->isActive() ? $appointment->whatsappReminderUrl($tenantCompany?->name) : null;
    // Con el módulo de consultas, "iniciar atención" abre la consulta clínica
    // y la cita se finaliza al cerrar esa consulta.
    $consultation = $appointment->consultation;
    $consultasOn = $tenantCompany?->planAllows('consultas') && $can('consultations.create');
    if ($consultasOn) {
        unset($actions['start']);
        if ($consultation?->isOpen()) {
            unset($actions['finish']);
        }
    }
    $canStartConsultation = $consultasOn && ! $consultation && in_array($appointment->status, ['pending', 'confirmed', 'waiting', 'in_progress'], true);
    $buttonStyles = [
        'confirm' => 'btn-primary', 'check_in' => 'btn-warning', 'start' => 'btn-info',
        'finish' => 'btn-success', 'no_show' => 'btn-outline-dark', 'cancel' => 'btn-outline-danger',
    ];
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">
                {{ $appointment->starts_at->translatedFormat('l j \d\e F') }}, {{ $appointment->starts_at->format('H:i') }}–{{ $appointment->ends_at->format('H:i') }}
            </h1>
            <span class="badge bg-{{ $appointment->status_color }} fs-6">{{ $appointment->status_label }}</span>
            @if($appointment->is_walk_in)<span class="badge bg-light text-dark border fs-6">Sin cita previa</span>@endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($can('appointments.edit') && $appointment->isActive())
                <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar / reprogramar</a>
            @endif
            <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary">Agenda</a>
        </div>
    </div>

    @if($pet?->hasClinicalWarnings())
        <div class="alert alert-danger d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                <strong>Alertas clínicas de {{ $pet->name }}:</strong>
                {{ $pet->clinical_alerts }} @if($pet->allergies) <strong>Alergias:</strong> {{ $pet->allergies }} @endif
            </div>
        </div>
    @endif

    {{-- Acciones de estado --}}
    @if($actions || $whatsapp || $canStartConsultation || $consultation)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                @if($canStartConsultation)
                    <form action="{{ route('consultations.start-from-appointment', $appointment) }}" method="POST" class="m-0">
                        @csrf
                        <button class="btn btn-info" type="submit"><i class="bi bi-clipboard2-pulse"></i> Iniciar consulta</button>
                    </form>
                @elseif($consultation && $can('consultations.view'))
                    <a href="{{ route($consultation->isOpen() && $can('consultations.edit') ? 'consultations.edit' : 'consultations.show', $consultation) }}" class="btn btn-info">
                        <i class="bi bi-clipboard2-pulse"></i> {{ $consultation->isOpen() ? 'Continuar consulta' : 'Ver consulta' }}
                    </a>
                @endif
                @foreach($actions as $action => $label)
                    @continue($action === 'cancel')
                    <form action="{{ route('appointments.transition', [$appointment, $action]) }}" method="POST" class="m-0">
                        @csrf
                        <button class="btn {{ $buttonStyles[$action] ?? 'btn-outline-secondary' }}" type="submit">{{ $label }}</button>
                    </form>
                @endforeach
                @if($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="bi bi-whatsapp"></i> Enviar recordatorio</a>
                @endif
                @isset($actions['cancel'])
                    <form action="{{ route('appointments.transition', [$appointment, 'cancel']) }}" method="POST" class="m-0 ms-auto d-flex gap-2"
                          onsubmit="return confirm('¿Cancelar esta cita?')">
                        @csrf
                        <input type="text" name="cancellation_reason" class="form-control form-control-sm" placeholder="Motivo de cancelación" maxlength="255">
                        <button class="btn btn-sm btn-outline-danger text-nowrap" type="submit">Cancelar cita</button>
                    </form>
                @endisset
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        @if($pet)
                            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 56])
                            <div>
                                <a href="{{ route('pets.show', $pet) }}" class="fs-5 fw-semibold text-decoration-none">{{ $pet->name }}</a>
                                <div class="small text-muted">
                                    {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }} · {{ $pet->sex_label }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}
                                </div>
                            </div>
                        @endif
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted fw-normal">Propietario</dt>
                        <dd class="col-sm-8">
                            @if($appointment->client)
                                <a href="{{ route('clients.show', $appointment->client) }}" class="text-decoration-none">{{ $appointment->client->full_name }}</a>
                                @if($appointment->client->phone)<span class="text-muted small"> · {{ $appointment->client->phone }}</span>@endif
                            @endif
                        </dd>
                        <dt class="col-sm-4 text-muted fw-normal">Servicio</dt>
                        <dd class="col-sm-8">{{ $appointment->service?->name ?? 'Sin especificar' }}</dd>
                        <dt class="col-sm-4 text-muted fw-normal">Profesional</dt>
                        <dd class="col-sm-8">{{ $appointment->veterinarian?->name ?? 'Sin asignar' }}</dd>
                        @if($appointment->branch)
                            <dt class="col-sm-4 text-muted fw-normal">Sucursal</dt>
                            <dd class="col-sm-8">{{ $appointment->branch->name }}</dd>
                        @endif
                        <dt class="col-sm-4 text-muted fw-normal">Duración</dt>
                        <dd class="col-sm-8">{{ $appointment->duration_minutes }} min</dd>
                        <dt class="col-sm-4 text-muted fw-normal">Motivo</dt>
                        <dd class="col-sm-8">{{ $appointment->reason ?: '—' }}</dd>
                        @if($appointment->notes)
                            <dt class="col-sm-4 text-muted fw-normal">Observaciones</dt>
                            <dd class="col-sm-8" style="white-space: pre-line">{{ $appointment->notes }}</dd>
                        @endif
                        @if($appointment->cancellation_reason)
                            <dt class="col-sm-4 text-muted fw-normal">Motivo de cancelación</dt>
                            <dd class="col-sm-8">{{ $appointment->cancellation_reason }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Historial</h6>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><i class="bi bi-plus-circle text-muted"></i> Agendada {{ $appointment->created_at->format('d/m/Y H:i') }}{{ $appointment->creator ? ' por ' . $appointment->creator->name : '' }}</li>
                        @foreach([
                            'confirmed_at' => ['bi-check2-circle text-primary', 'Confirmada'],
                            'checked_in_at' => ['bi-door-open text-warning', 'Llegó a la veterinaria'],
                            'started_at' => ['bi-play-circle text-info', 'Inicio de la atención'],
                            'finished_at' => ['bi-check-circle-fill text-success', 'Atención finalizada'],
                            'cancelled_at' => ['bi-x-circle text-danger', 'Cancelada'],
                        ] as $field => [$icon, $label])
                            @if($appointment->$field)
                                <li class="mb-2"><i class="bi {{ $icon }}"></i> {{ $label }} {{ $appointment->$field->format('d/m/Y H:i') }}</li>
                            @endif
                        @endforeach
                        @if($appointment->waiting_minutes !== null)
                            <li class="mb-2"><i class="bi bi-hourglass-split text-muted"></i> Espera: {{ $appointment->waiting_minutes }} min</li>
                        @endif
                    </ul>
                </div>
            </div>

            @if($can('appointments.delete'))
                <form action="{{ route('appointments.destroy', $appointment) }}" method="POST" class="mt-3 text-end"
                      onsubmit="return confirm('¿Eliminar definitivamente esta cita? Para dejar constancia es mejor cancelarla.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-link text-danger" type="submit"><i class="bi bi-trash"></i> Eliminar cita</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
