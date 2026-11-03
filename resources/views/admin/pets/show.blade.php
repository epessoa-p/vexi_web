@extends('layouts.app')

@section('title', "Ficha de {$pet->name}")

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $canEdit = $can('pets.edit');
    $agenda = $can('appointments.view') && $tenantCompany?->planAllows('agenda');
    $canSchedule = $agenda && $can('appointments.create') && $pet->status === 'active';
    $consultasView = $can('consultations.view') && $tenantCompany?->planAllows('consultas');
    $canConsult = $consultasView && $can('consultations.create') && $pet->status === 'active';
    $healthView = $can('vaccinations.view') && $tenantCompany?->planAllows('vacunas');
    $canHealth = $healthView && $can('vaccinations.create') && $pet->status === 'active';
    $overdueHealth = $healthView ? $pendingHealth->filter(fn ($p) => $p['due']->lt(now()->startOfDay())) : collect();
    $tab = in_array(request('tab'), ['history', 'weight', 'health', 'record'], true) ? request('tab') : 'history';
    if ($errors->hasAny(['weight', 'recorded_at'])) {
        $tab = 'weight';
    }
    $weights = $pet->weights;
@endphp
<div class="container-fluid">
    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 72])
            <div>
                <h1 class="mb-1">
                    {{ $pet->name }}
                    <span class="badge fs-6 align-middle {{ match ($pet->status) { 'active' => 'bg-success', 'deceased' => 'bg-dark', default => 'bg-secondary' } }}">{{ $pet->status_label }}</span>
                </h1>
                <div class="text-muted">
                    {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }}
                    · {{ $pet->sex_label }}{{ $pet->sterilized ? ' (esterilizado)' : '' }}
                    @if($pet->age_label) · {{ $pet->age_label }} @endif
                    @if($pet->weight) · {{ rtrim(rtrim(number_format($pet->weight, 2), '0'), '.') }} kg @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($canConsult)
                <a href="{{ route('consultations.create', ['pet_id' => $pet->id]) }}" class="btn btn-info"><i class="bi bi-clipboard2-pulse"></i> Nueva consulta</a>
            @endif
            @if($canSchedule)
                <a href="{{ route('appointments.create', ['pet_id' => $pet->id]) }}" class="btn btn-success"><i class="bi bi-calendar-plus"></i> Agendar cita</a>
            @endif
            @if($canEdit)
                <a href="{{ route('pets.edit', $pet) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar datos</a>
            @endif
            @if($can('pets.delete'))
                <form action="{{ route('pets.destroy', $pet) }}" method="POST" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Eliminar a {{ $pet->name }}? Su ficha dejará de estar visible.')"><i class="bi bi-trash"></i></button>
                </form>
            @endif
            <a href="{{ route('pets.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if($overdueHealth->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2 align-items-center py-2">
            <i class="bi bi-shield-exclamation fs-5"></i>
            <div><strong>Atrasado:</strong> {{ $overdueHealth->map(fn ($p) => $p['title'] . ' (desde ' . $p['due']->format('d/m/Y') . ')')->implode(' · ') }}</div>
            <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'health']) }}" class="btn btn-sm btn-outline-dark ms-auto text-nowrap">Ver vacunas</a>
        </div>
    @endif

    {{-- Alertas clínicas: lo primero que debe ver quien atiende --}}
    @if($pet->hasClinicalWarnings())
        <div class="alert alert-danger d-flex gap-3 align-items-start" role="alert">
            <i class="bi bi-exclamation-octagon-fill fs-4"></i>
            <div>
                <strong>Alertas clínicas</strong>
                @if($pet->clinical_alerts)<div style="white-space: pre-line">{{ $pet->clinical_alerts }}</div>@endif
                @if($pet->allergies)<div><strong>Alergias:</strong> {{ $pet->allergies }}</div>@endif
            </div>
        </div>
    @endif

    <div class="row g-4">
        {{-- Columna izquierda: datos generales y propietario --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-card-list"></i> Datos generales</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-6 text-muted fw-normal">Nacimiento</dt>
                        <dd class="col-6">{{ $pet->birth_date?->format('d/m/Y') ?? '-' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Tamaño</dt>
                        <dd class="col-6">{{ $pet->size_label ?? '-' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Color</dt>
                        <dd class="col-6">{{ $pet->color ?: '-' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Microchip</dt>
                        <dd class="col-6 text-break">{{ $pet->microchip ?: '-' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Identificación</dt>
                        <dd class="col-6">{{ $pet->identification ?: '-' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Fecha de registro</dt>
                        <dd class="col-6">{{ $pet->created_at->format('d/m/Y') }}</dd>
                    </dl>
                    @if($pet->notes)
                        <hr>
                        <div class="small text-muted mb-1">Observaciones</div>
                        <p class="small mb-0" style="white-space: pre-line">{{ $pet->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Propietario</h6>
                    @if($pet->client)
                        <a href="{{ route('clients.show', $pet->client) }}" class="fw-semibold text-decoration-none">{{ $pet->client->full_name }}</a>
                        <div class="small text-muted mt-1">
                            @if($pet->client->document_number)<div>{{ $pet->client->document_type }} {{ $pet->client->document_number }}</div>@endif
                            @if($pet->client->phone)<div><i class="bi bi-telephone"></i> {{ $pet->client->phone }}</div>@endif
                            @if($pet->client->whatsapp_url)
                                <div><a href="{{ $pet->client->whatsapp_url }}" target="_blank" rel="noopener" class="text-success text-decoration-none"><i class="bi bi-whatsapp"></i> {{ $pet->client->whatsapp }}</a></div>
                            @endif
                            @if($pet->client->email)<div><i class="bi bi-envelope"></i> {{ $pet->client->email }}</div>@endif
                        </div>
                    @endif
                </div>
            </div>

            @if($agenda)
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-calendar-week"></i> Citas</h6>
                        @forelse($upcomingAppointments->concat($pastAppointments) as $appointment)
                            <a href="{{ route('appointments.show', $appointment) }}" class="d-flex justify-content-between align-items-center py-2 border-bottom text-decoration-none text-reset small">
                                <div>
                                    <div class="fw-semibold">{{ $appointment->starts_at->format('d/m/Y H:i') }}</div>
                                    <div class="text-muted">{{ $appointment->service?->name ?? 'Sin servicio' }}{{ $appointment->veterinarian ? ' · ' . $appointment->veterinarian->name : '' }}</div>
                                </div>
                                <span class="badge bg-{{ $appointment->status_color }}">{{ $appointment->status_label }}</span>
                            </a>
                        @empty
                            <p class="small text-muted mb-0">Sin citas registradas.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>

        {{-- Columna derecha: ficha clínica --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-3">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link {{ $tab === 'history' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-history" type="button"><i class="bi bi-journal-medical"></i> Antecedentes</button></li>
                        <li class="nav-item"><button class="nav-link {{ $tab === 'weight' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-weight" type="button"><i class="bi bi-speedometer2"></i> Peso</button></li>
                        @if($healthView)
                            <li class="nav-item"><button class="nav-link {{ $tab === 'health' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-health" type="button"><i class="bi bi-shield-plus"></i> Vacunas</button></li>
                        @endif
                        <li class="nav-item"><button class="nav-link {{ $tab === 'record' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-record" type="button"><i class="bi bi-folder2-open"></i> Expediente</button></li>
                    </ul>
                </div>
                <div class="card-body tab-content">
                    {{-- Antecedentes --}}
                    <div class="tab-pane fade {{ $tab === 'history' ? 'show active' : '' }}" id="tab-history">
                        @php
                            $historyFields = [
                                'clinical_alerts' => ['Alertas clínicas', 'Se muestra destacada al abrir la ficha. Ej.: agresivo, no tolera anestesia…', 2],
                                'allergies' => ['Alergias', 'Medicamentos, alimentos, vacunas…', 2],
                                'chronic_conditions' => ['Enfermedades crónicas', null, 2],
                                'previous_surgeries' => ['Cirugías anteriores', null, 2],
                                'usual_medications' => ['Medicamentos habituales', null, 2],
                                'medical_history' => ['Antecedentes médicos', 'Enfermedades previas, tratamientos, procedencia…', 4],
                            ];
                        @endphp
                        @if($canEdit)
                            <form action="{{ route('pets.history.update', $pet) }}" method="POST" class="row g-3">
                                @csrf
                                @method('PUT')
                                @foreach($historyFields as $field => [$label, $help, $rows])
                                    <div class="{{ $rows > 2 ? 'col-12' : 'col-md-6' }}">
                                        <label class="form-label {{ $field === 'clinical_alerts' ? 'text-danger fw-semibold' : '' }}">{{ $label }}</label>
                                        <textarea name="{{ $field }}" rows="{{ $rows }}" class="form-control">{{ old($field, $pet->$field) }}</textarea>
                                        @if($help)<div class="form-text">{{ $help }}</div>@endif
                                    </div>
                                @endforeach
                                <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar antecedentes</button></div>
                            </form>
                        @else
                            <dl class="row mb-0">
                                @foreach($historyFields as $field => [$label])
                                    <dt class="col-sm-4 text-muted fw-normal">{{ $label }}</dt>
                                    <dd class="col-sm-8" style="white-space: pre-line">{{ $pet->$field ?: '—' }}</dd>
                                @endforeach
                            </dl>
                        @endif
                    </div>

                    {{-- Historial de peso --}}
                    <div class="tab-pane fade {{ $tab === 'weight' ? 'show active' : '' }}" id="tab-weight">
                        @if($canEdit)
                            <form action="{{ route('pets.weights.store', $pet) }}" method="POST" class="row g-2 align-items-end mb-4">
                                @csrf
                                <div class="col-sm-3">
                                    <label class="form-label small">Peso (kg)</label>
                                    <input type="number" step="0.01" min="0.01" name="weight" class="form-control @error('weight') is-invalid @enderror" value="{{ old('weight') }}" required>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label small">Fecha</label>
                                    <input type="date" name="recorded_at" class="form-control @error('recorded_at') is-invalid @enderror" value="{{ old('recorded_at', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label small">Nota</label>
                                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" maxlength="255">
                                </div>
                                <div class="col-sm-2"><button class="btn btn-success w-100" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button></div>
                            </form>
                        @endif

                        @if($weights->count() > 1)
                            @php
                                // Mini gráfico de evolución (cronológico, de izquierda a derecha).
                                $points = $weights->sortBy('recorded_at')->values();
                                $min = (float) $points->min('weight');
                                $max = (float) $points->max('weight');
                                $range = max($max - $min, 0.01);
                                $w = 600; $h = 120; $pad = 10;
                                $coords = $points->map(function ($p, $i) use ($points, $min, $range, $w, $h, $pad) {
                                    $x = $points->count() > 1 ? $pad + $i * ($w - 2 * $pad) / ($points->count() - 1) : $w / 2;
                                    $y = $h - $pad - (((float) $p->weight - $min) / $range) * ($h - 2 * $pad);
                                    return round($x, 1) . ',' . round($y, 1);
                                });
                            @endphp
                            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-100 mb-3 border rounded bg-light" style="height: 120px" preserveAspectRatio="none" role="img" aria-label="Evolución del peso">
                                <polyline fill="none" stroke="currentColor" class="text-primary" stroke-width="2.5" points="{{ $coords->implode(' ') }}" vector-effect="non-scaling-stroke"/>
                            </svg>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Fecha</th><th>Peso</th><th>Variación</th><th>Nota</th><th>Registró</th><th></th></tr></thead>
                                <tbody>
                                    @forelse($weights as $i => $record)
                                        @php $previous = $weights[$i + 1] ?? null; $delta = $previous ? (float) $record->weight - (float) $previous->weight : null; @endphp
                                        <tr>
                                            <td>{{ $record->recorded_at->format('d/m/Y') }}</td>
                                            <td class="fw-semibold">{{ number_format($record->weight, 2) }} kg</td>
                                            <td>
                                                @if($delta !== null && abs($delta) >= 0.005)
                                                    <span class="{{ $delta > 0 ? 'text-success' : 'text-danger' }}">
                                                        <i class="bi bi-arrow-{{ $delta > 0 ? 'up' : 'down' }}"></i> {{ number_format(abs($delta), 2) }} kg
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="small">{{ $record->notes }}</td>
                                            <td class="small text-muted">{{ $record->recorder?->name ?? '-' }}</td>
                                            <td class="text-end">
                                                @if($canEdit)
                                                    <form action="{{ route('pets.weights.destroy', [$pet, $record]) }}" method="POST" class="m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-sm btn-link text-danger p-0" type="submit" onclick="return confirm('¿Eliminar este registro de peso?')" title="Eliminar"><i class="bi bi-x-circle"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-4">Sin registros de peso.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($healthView)
                        @include('admin.pets.partials.health-tab')
                    @endif

                    {{-- Expediente clínico: cada módulo clínico aporta su historial --}}
                    <div class="tab-pane fade {{ $tab === 'record' ? 'show active' : '' }}" id="tab-record">
                        @if($consultasView)
                            <h6 class="fw-bold"><i class="bi bi-clipboard2-pulse"></i> Consultas ({{ $consultations->count() }})</h6>
                            @forelse($consultations as $consultation)
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <a href="{{ route('consultations.show', $consultation) }}" class="fw-semibold text-decoration-none">{{ $consultation->started_at->format('d/m/Y') }}</a>
                                            <span class="badge bg-{{ $consultation->status_color }}">{{ $consultation->status_label }}</span>
                                            <span class="small text-muted">· {{ $consultation->veterinarian?->name }}</span>
                                            @if($consultation->reason)<div class="small text-muted">Motivo: {{ $consultation->reason }}</div>@endif
                                        </div>
                                        @if($consultation->prescriptionItems->isNotEmpty())
                                            <a href="{{ route('consultations.prescription', $consultation) }}" target="_blank" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-printer"></i> {{ $consultation->prescription_number }}</a>
                                        @endif
                                    </div>
                                    @if($consultation->diagnoses->isNotEmpty())
                                        <div class="small mt-2"><span class="text-muted">Diagnóstico:</span>
                                            {{ $consultation->diagnoses->map(fn ($d) => $d->description . ($d->type !== 'primary' ? " ({$d->type_label})" : ''))->implode('; ') }}
                                        </div>
                                    @endif
                                    @if($consultation->prescriptionItems->isNotEmpty())
                                        <div class="small"><span class="text-muted">Tratamiento:</span>
                                            {{ $consultation->prescriptionItems->map(fn ($p) => "{$p->medication} {$p->dose} {$p->frequency}")->implode('; ') }}
                                        </div>
                                    @endif
                                    @if($consultation->procedures->isNotEmpty())
                                        <div class="small"><span class="text-muted">Procedimientos:</span> {{ $consultation->procedures->pluck('name')->implode('; ') }}</div>
                                    @endif
                                    @if($last = $consultation->followups->first())
                                        <div class="small"><span class="text-muted">Último seguimiento:</span> <span class="badge bg-{{ $last->status_color }}">{{ $last->status_label }}</span> {{ $last->recorded_at->format('d/m/Y') }}</div>
                                    @endif
                                </div>
                            @empty
                                <p class="text-muted small">Sin consultas registradas.</p>
                            @endforelse
                            <hr>
                        @endif
                        <p class="text-muted small">Próximamente en el expediente:</p>
                        <div class="row g-2">
                            @foreach([
                                ['bi-scissors', 'Cirugías'],
                                ['bi-hospital', 'Hospitalizaciones'],
                                ['bi-eyedropper', 'Resultados de laboratorio'],
                                ['bi-paperclip', 'Archivos adjuntos'],
                            ] as [$icon, $label])
                                <div class="col-md-6">
                                    <div class="border rounded p-3 d-flex align-items-center gap-2 text-muted">
                                        <i class="bi {{ $icon }} fs-5"></i>
                                        <span class="flex-grow-1">{{ $label }}</span>
                                        <span class="badge bg-light text-muted border">Próximamente</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
