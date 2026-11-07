@extends('layouts.app')

@section('title', "Consulta de {$consultation->pet?->name}")

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $c = $consultation;
    $pet = $c->pet;
    $canSchedule = $c->next_review_at && $can('appointments.create') && $tenantCompany?->planAllows('agenda');
    $canDispense = ! $c->isOpen() && $tenantCompany?->planAllows('inventario') && ($can('inventory.create') || $can('consultations.edit'));
    $vitals = array_filter([
        'Temperatura' => $c->temperature !== null ? "{$c->temperature} °C" : null,
        'Peso' => $c->weight !== null ? rtrim(rtrim(number_format($c->weight, 2), '0'), '.') . ' kg' : null,
        'F. cardíaca' => $c->heart_rate ? "{$c->heart_rate} lpm" : null,
        'F. respiratoria' => $c->respiratory_rate ? "{$c->respiratory_rate} rpm" : null,
        'Hidratación' => \App\Models\Consultation::HYDRATION[$c->hydration] ?? null,
        'Condición corporal' => \App\Models\Consultation::BODY_CONDITION[$c->body_condition] ?? null,
        'Mucosas' => \App\Models\Consultation::MUCOUS_MEMBRANES[$c->mucous_membranes] ?? null,
    ]);
@endphp
<div class="container-fluid" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 64])
            <div>
                <h1 class="mb-0 h3">Consulta de {{ $pet->name }} <span class="badge bg-{{ $c->status_color }} fs-6 align-middle">{{ $c->status_label }}</span></h1>
                <div class="text-muted small">
                    {{ $c->started_at->translatedFormat('j \d\e F \d\e Y, H:i') }}
                    @if($c->finished_at) – finalizada {{ $c->finished_at->format('d/m/Y H:i') }} @endif
                    · {{ $c->veterinarian?->name ?? 'Sin profesional' }}
                    · <a href="{{ route('pets.show', $pet) }}" class="text-decoration-none">Ficha de {{ $pet->name }}</a>
                    · {{ $c->client?->full_name }}
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($c->isOpen() && $can('consultations.edit'))
                <a href="{{ route('consultations.edit', $c) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Continuar consulta</a>
            @endif
            @if($c->prescriptionItems->isNotEmpty() || $c->instructions)
                <a href="{{ route('consultations.prescription', $c) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Receta</a>
            @endif
            @if($tenantCompany?->planAllows('hospitalizacion') && $can('hospitalizations.create') && $pet->status === 'active')
                <a href="{{ route('hospitalizations.create', ['consultation_id' => $c->id]) }}" class="btn btn-outline-primary"><i class="bi bi-hospital"></i> Internar</a>
            @endif
            @if(! $c->isOpen() && $tenantCompany?->planAllows('pos'))
                @if($c->sale_id && $can('sales.view'))
                    <a href="{{ route('sales.show', $c->sale_id) }}" class="btn btn-outline-success"><i class="bi bi-receipt"></i> Cobrada</a>
                @elseif(! $c->sale_id && $can('sales.create'))
                    <a href="{{ route('pos.create', ['pet_id' => $pet->id]) }}" class="btn btn-success"><i class="bi bi-cash-coin"></i> Cobrar</a>
                @endif
            @endif
            @if($canSchedule)
                <a href="{{ route('appointments.create', ['pet_id' => $pet->id, 'start' => $c->next_review_at->format('Y-m-d') . ' 09:00']) }}" class="btn btn-outline-success">
                    <i class="bi bi-calendar-plus"></i> Agendar revisión ({{ $c->next_review_at->format('d/m') }})
                </a>
            @endif
            <a href="{{ route('consultations.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Evaluación --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse"></i> Evaluación clínica</h6>
                    @if($c->reason)<p class="mb-2"><span class="text-muted">Motivo:</span> {{ $c->reason }}</p>@endif
                    @if($vitals)
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach($vitals as $label => $value)
                                <div class="border rounded px-2 py-1 small"><span class="text-muted">{{ $label }}:</span> <strong>{{ $value }}</strong></div>
                            @endforeach
                        </div>
                    @endif
                    @foreach(['anamnesis' => 'Anamnesis', 'physical_exam' => 'Examen físico', 'clinical_notes' => 'Observaciones clínicas'] as $field => $label)
                        @if($c->$field)
                            <div class="small text-muted mt-2">{{ $label }}</div>
                            <p class="mb-2" style="white-space: pre-line">{{ $c->$field }}</p>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Diagnóstico --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-search-heart"></i> Diagnóstico</h6>
                    @forelse($c->diagnoses as $diagnosis)
                        <div class="d-flex gap-2 align-items-start mb-2">
                            <span class="badge {{ $diagnosis->type === 'primary' ? 'bg-primary' : 'bg-light text-dark border' }}">{{ $diagnosis->type_label }}</span>
                            <div>
                                {{ $diagnosis->description }} @if($diagnosis->code)<small class="text-muted">[{{ $diagnosis->code }}]</small>@endif
                                @if($diagnosis->notes)<div class="small text-muted">{{ $diagnosis->notes }}</div>@endif
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin diagnóstico registrado.</p>
                    @endforelse
                </div>
            </div>

            {{-- Tratamiento --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-prescription2"></i> Tratamiento</h6>
                    @if($c->prescriptionItems->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light"><tr><th>Medicamento</th><th>Dosis</th><th>Vía</th><th>Frecuencia</th><th>Duración</th>@if($canDispense)<th class="text-end">Farmacia</th>@endif</tr></thead>
                                <tbody>
                                    @foreach($c->prescriptionItems as $item)
                                        <tr>
                                            <td>
                                                <strong>{{ $item->medication }}</strong> @if($item->presentation)<small class="text-muted">{{ $item->presentation }}</small>@endif
                                                @if($item->instructions)<div class="small text-muted">{{ $item->instructions }}</div>@endif
                                            </td>
                                            <td>{{ $item->dose }}</td>
                                            <td>{{ $item->route_label }}</td>
                                            <td>{{ $item->frequency }}</td>
                                            <td>{{ $item->duration ?: '—' }}</td>
                                            @if($canDispense)
                                                <td class="text-end text-nowrap">
                                                    @if($item->dispensed_at)
                                                        <span class="badge bg-success" title="{{ $item->dispensed_at->format('d/m/Y H:i') }}"><i class="bi bi-check2"></i> Dispensado ({{ \App\Support\Stock::number($item->dispensed_quantity) }})</span>
                                                    @elseif($item->product_id)
                                                        <form action="{{ route('consultations.dispense', [$c, $item]) }}" method="POST" class="d-inline-flex gap-1">
                                                            @csrf
                                                            <input type="number" name="quantity" step="0.01" min="0.01" value="{{ is_numeric($item->quantity) ? $item->quantity : 1 }}" class="form-control form-control-sm" style="width: 5rem" title="Cantidad a entregar">
                                                            <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-bag-check"></i> Dispensar</button>
                                                        </form>
                                                    @else
                                                        <span class="small text-muted">No vinculado</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @foreach(['instructions' => 'Indicaciones al propietario', 'recommendations' => 'Recomendaciones'] as $field => $label)
                        @if($c->$field)
                            <div class="small text-muted">{{ $label }}</div>
                            <p class="mb-2" style="white-space: pre-line">{{ $c->$field }}</p>
                        @endif
                    @endforeach
                    @if($c->prescriptionItems->isEmpty() && ! $c->instructions && ! $c->recommendations)
                        <p class="text-muted small mb-0">Sin tratamiento indicado.</p>
                    @endif
                    @if($c->next_review_at)
                        <p class="mb-0 mt-2"><i class="bi bi-calendar-check"></i> Próxima revisión: <strong>{{ $c->next_review_at->format('d/m/Y') }}</strong></p>
                    @endif
                </div>
            </div>

            @if($c->supplies->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-box-seam"></i> Insumos utilizados</h6>
                        <ul class="list-unstyled mb-0 small">
                            @foreach($c->supplies as $supply)
                                <li class="d-flex justify-content-between border-bottom py-1">
                                    <span>{{ $supply->product?->label }}{{ $supply->notes ? " · {$supply->notes}" : '' }}</span>
                                    <span class="fw-semibold">{{ \App\Support\Stock::number($supply->quantity) }} {{ $supply->product?->unit }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if($tenantCompany?->planAllows('laboratorio') && $can('lab_requests.view'))
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0"><i class="bi bi-eyedropper"></i> Laboratorio</h6>
                            @if($can('lab_requests.create'))
                                <a href="{{ route('lab.create', ['pet_id' => $pet->id, 'consultation_id' => $c->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Solicitar</a>
                            @endif
                        </div>
                        @forelse($c->labRequests as $lab)
                            <a href="{{ route('lab.show', $lab) }}" class="d-flex justify-content-between border-top py-1 small text-decoration-none text-reset">
                                <span>{{ $lab->studyType?->name }}</span>
                                <span class="badge bg-{{ $lab->status_color }}">{{ $lab->status_label }}</span>
                            </a>
                        @empty
                            <p class="small text-muted mb-0">Sin estudios solicitados.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if($c->procedures->isNotEmpty())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-bandaid"></i> Procedimientos</h6>
                        @foreach($c->procedures as $procedure)
                            <div class="border rounded p-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <strong>{{ $procedure->name }}</strong>
                                    <span>{{ \App\Support\Money::format($procedure->cost, $tenantCompany) }}</span>
                                </div>
                                <div class="small text-muted">Responsable: {{ $procedure->performer?->name ?? '—' }}</div>
                                @if($procedure->description)<div class="small">{{ $procedure->description }}</div>@endif
                                @if($procedure->materials)<div class="small"><span class="text-muted">Materiales:</span> {{ $procedure->materials }}</div>@endif
                                @if($procedure->medications)<div class="small"><span class="text-muted">Medicamentos:</span> {{ $procedure->medications }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Seguimiento --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow"></i> Seguimiento</h6>
                    @if($can('consultations.edit'))
                        <form action="{{ route('consultations.followups.store', $c) }}" method="POST" class="mb-3">
                            @csrf
                            <select name="treatment_status" class="form-select form-select-sm mb-2">
                                @foreach(\App\Models\ConsultationFollowup::TREATMENT_STATUSES as $value => [$label])
                                    <option value="{{ $value }}" @selected(old('treatment_status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <textarea name="notes" rows="3" class="form-control form-control-sm mb-2 @error('notes') is-invalid @enderror" placeholder="Evolución del paciente, observaciones posteriores…" required>{{ old('notes') }}</textarea>
                            <button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-plus-lg"></i> Registrar evolución</button>
                        </form>
                    @endif
                    @forelse($c->followups as $followup)
                        <div class="border-start border-3 border-{{ $followup->status_color }} ps-2 mb-3 small">
                            <div class="d-flex justify-content-between">
                                <span class="badge bg-{{ $followup->status_color }}">{{ $followup->status_label }}</span>
                                <span class="text-muted">{{ $followup->recorded_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mb-0 mt-1" style="white-space: pre-line">{{ $followup->notes }}</p>
                            <div class="text-muted">{{ $followup->recorder?->name }}</div>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Sin seguimientos registrados.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
