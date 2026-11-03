@extends('layouts.app')

@section('title', "Consulta de {$consultation->pet?->name}")

@section('page')
@php
    $pet = $consultation->pet;
    $c = $consultation;
    $diagnoses = old('diagnoses', $c->diagnoses->map->only(['type', 'code', 'description', 'notes'])->all());
    $prescription = old('prescription', $c->prescriptionItems->map->only(['product_id', 'medication', 'presentation', 'dose', 'route', 'frequency', 'duration', 'quantity', 'instructions'])->all());
    $supplies = old('supplies', $c->supplies->map(fn ($s) => [
        'product_id' => $s->product_id, 'product_label' => $s->product?->label, 'quantity' => $s->quantity, 'notes' => $s->notes,
    ])->all());
    $withInventory = $inventoryProducts->isNotEmpty();
    $procedures = old('procedures', $c->procedures->map->only(['name', 'description', 'materials', 'medications', 'performed_by', 'cost'])->all());
    $routes = \App\Models\PrescriptionItem::ROUTES;
    $types = \App\Models\ConsultationDiagnosis::TYPES;
    $canDelete = auth()->user()->hasPermissionInCompany('consultations.delete', $tenantCompany);
@endphp
<div class="container-fluid">
    {{-- Encabezado del paciente --}}
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 64])
            <div>
                <h1 class="mb-0 h3">Consulta de {{ $pet->name }} <span class="badge bg-info fs-6 align-middle">En proceso</span></h1>
                <div class="text-muted small">
                    {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }} · {{ $pet->sex_label }}{{ $pet->sterilized ? ' (esterilizado)' : '' }}
                    @if($pet->age_label) · {{ $pet->age_label }} @endif
                    · Propietario: {{ $c->client?->full_name }}
                    · Inicio {{ $c->started_at->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
        <a href="{{ route('pets.show', $pet) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Ficha completa</a>
    </div>

    @if($pet->hasClinicalWarnings())
        <div class="alert alert-danger d-flex gap-2 align-items-start py-2">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                @if($pet->clinical_alerts)<strong>Alerta:</strong> {{ $pet->clinical_alerts }}@endif
                @if($pet->allergies) <strong>Alergias:</strong> {{ $pet->allergies }}@endif
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <form action="{{ route('consultations.update', $c) }}" method="POST" id="consultation-form">
                @csrf
                @method('PUT')

                {{-- 1. Evaluación clínica --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse"></i> Evaluación clínica</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Motivo de consulta</label>
                                <input type="text" name="reason" class="form-control" value="{{ old('reason', $c->reason) }}" maxlength="255">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Profesional</label>
                                <select name="veterinarian_id" class="form-select @error('veterinarian_id') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach($professionals as $professional)
                                        <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id', $c->veterinarian_id) === (string) $professional->id)>{{ $professional->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Anamnesis</label>
                                <textarea name="anamnesis" rows="3" class="form-control" placeholder="Historia del problema actual según el propietario: inicio, evolución, apetito, vómitos, deposiciones…">{{ old('anamnesis', $c->anamnesis) }}</textarea>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Temperatura (°C)</label>
                                <input type="number" step="0.1" name="temperature" class="form-control @error('temperature') is-invalid @enderror" value="{{ old('temperature', $c->temperature) }}">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Peso (kg)</label>
                                <input type="number" step="0.01" name="weight" class="form-control @error('weight') is-invalid @enderror" value="{{ old('weight', $c->weight) }}" placeholder="{{ $pet->weight ? 'Último: ' . rtrim(rtrim(number_format($pet->weight, 2), '0'), '.') : '' }}">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">F. cardíaca (lpm)</label>
                                <input type="number" name="heart_rate" class="form-control @error('heart_rate') is-invalid @enderror" value="{{ old('heart_rate', $c->heart_rate) }}">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">F. respiratoria (rpm)</label>
                                <input type="number" name="respiratory_rate" class="form-control @error('respiratory_rate') is-invalid @enderror" value="{{ old('respiratory_rate', $c->respiratory_rate) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Hidratación</label>
                                <select name="hydration" class="form-select">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Consultation::HYDRATION as $value => $label)
                                        <option value="{{ $value }}" @selected(old('hydration', $c->hydration) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Condición corporal (1–9)</label>
                                <select name="body_condition" class="form-select">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Consultation::BODY_CONDITION as $value => $label)
                                        <option value="{{ $value }}" @selected((string) old('body_condition', $c->body_condition) === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mucosas</label>
                                <select name="mucous_membranes" class="form-select">
                                    <option value="">—</option>
                                    @foreach(\App\Models\Consultation::MUCOUS_MEMBRANES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('mucous_membranes', $c->mucous_membranes) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Examen físico</label>
                                <textarea name="physical_exam" rows="3" class="form-control" placeholder="Hallazgos por sistemas: piel, ojos, oídos, cavidad oral, linfonodos, abdomen…">{{ old('physical_exam', $c->physical_exam) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observaciones clínicas</label>
                                <textarea name="clinical_notes" rows="2" class="form-control">{{ old('clinical_notes', $c->clinical_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Diagnóstico --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-search-heart"></i> Diagnóstico</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="diagnoses"><i class="bi bi-plus-lg"></i> Agregar diagnóstico</button>
                        </div>
                        @error('diagnoses')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror
                        <div id="rows-diagnoses" data-next="{{ count($diagnoses) }}">
                            @foreach($diagnoses as $i => $row)
                                @include('admin.consultations.partials.diagnosis-row', ['i' => $i, 'row' => $row])
                            @endforeach
                        </div>
                        <p class="small text-muted mb-0 empty-hint">Al finalizar se exige al menos un diagnóstico principal.</p>
                    </div>
                </div>

                {{-- 3. Tratamiento y receta --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-prescription2"></i> Tratamiento y receta</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="prescription"><i class="bi bi-plus-lg"></i> Agregar medicamento</button>
                        </div>
                        <div id="rows-prescription" data-next="{{ count($prescription) }}">
                            @foreach($prescription as $i => $row)
                                @include('admin.consultations.partials.prescription-row', ['i' => $i, 'row' => $row])
                            @endforeach
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Indicaciones al propietario <small class="text-muted">(salen en la receta)</small></label>
                                <textarea name="instructions" rows="3" class="form-control" placeholder="Cómo administrar, cuidados en casa, signos de alarma…">{{ old('instructions', $c->instructions) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Recomendaciones</label>
                                <textarea name="recommendations" rows="3" class="form-control" placeholder="Dieta, reposo, controles…">{{ old('recommendations', $c->recommendations) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4. Procedimientos --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-bandaid"></i> Procedimientos realizados</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="procedures"><i class="bi bi-plus-lg"></i> Agregar procedimiento</button>
                        </div>
                        <div id="rows-procedures" data-next="{{ count($procedures) }}">
                            @foreach($procedures as $i => $row)
                                @include('admin.consultations.partials.procedure-row', ['i' => $i, 'row' => $row])
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- 4b. Insumos (inventario) --}}
                @if($withInventory)
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold mb-0"><i class="bi bi-box-seam"></i> Insumos y medicamentos utilizados</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-add-row="supplies"><i class="bi bi-plus-lg"></i> Agregar insumo</button>
                            </div>
                            <p class="small text-muted">Lo que se usó en la atención y sus procedimientos. Se descuenta del inventario al finalizar la consulta.</p>
                            @error('supplies.*.product_id')<div class="alert alert-warning py-2">Elige cada insumo de la lista del inventario.</div>@enderror
                            <div id="rows-supplies" data-next="{{ count($supplies) }}">
                                @foreach($supplies as $i => $row)
                                    @include('admin.consultations.partials.supply-row', ['i' => $i, 'row' => $row])
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- 5. Seguimiento --}}
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-4 row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label"><i class="bi bi-calendar-check"></i> Próxima revisión</label>
                            <input type="date" name="next_review_at" class="form-control @error('next_review_at') is-invalid @enderror" value="{{ old('next_review_at', $c->next_review_at?->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-8 small text-muted">Al finalizar, podrás agendar la revisión con un clic desde la consulta.</div>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap sticky-bottom bg-body py-3 border-top">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i> Guardar borrador</button>
                    <button class="btn btn-success" type="submit" name="finish" value="1"
                            onclick="return confirm('Al finalizar, la consulta queda cerrada y ya no se puede editar. ¿Continuar?')">
                        <i class="bi bi-check2-circle"></i> Guardar y finalizar
                    </button>
                    @if($canDelete)
                        <button class="btn btn-link text-danger ms-auto" type="submit" form="discard-form"
                                onclick="return confirm('¿Descartar este borrador de consulta?')"><i class="bi bi-trash"></i> Descartar</button>
                    @endif
                </div>
            </form>
            @if($canDelete)
                <form id="discard-form" action="{{ route('consultations.destroy', $c) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
            @endif
        </div>

        {{-- Contexto clínico del paciente --}}
        <div class="col-xl-4">
            @if($tenantCompany?->planAllows('vacunas') && auth()->user()->hasPermissionInCompany('vaccinations.create', $tenantCompany))
                {{-- En otra pestaña, para no perder lo que se está escribiendo. --}}
                <div class="d-flex gap-2 mb-3">
                    <a href="{{ route('vaccinations.create', ['pet_id' => $pet->id, 'consultation_id' => $c->id]) }}" target="_blank" class="btn btn-sm btn-outline-success flex-fill"><i class="bi bi-shield-plus"></i> Aplicar vacuna</a>
                    <a href="{{ route('dewormings.create', ['pet_id' => $pet->id, 'consultation_id' => $c->id]) }}" target="_blank" class="btn btn-sm btn-outline-success flex-fill"><i class="bi bi-bug"></i> Desparasitar</a>
                </div>
            @endif
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body small">
                    <h6 class="fw-bold mb-3"><i class="bi bi-journal-medical"></i> Antecedentes</h6>
                    @foreach([
                        'chronic_conditions' => 'Enfermedades crónicas',
                        'usual_medications' => 'Medicamentos habituales',
                        'previous_surgeries' => 'Cirugías anteriores',
                        'medical_history' => 'Antecedentes médicos',
                    ] as $field => $label)
                        <div class="mb-2"><span class="text-muted">{{ $label }}:</span> {{ $pet->$field ?: '—' }}</div>
                    @endforeach
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body small">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Consultas anteriores</h6>
                    @forelse($previousConsultations as $previous)
                        <a href="{{ route('consultations.show', $previous) }}" target="_blank" class="d-block py-2 border-bottom text-decoration-none text-reset">
                            <div class="fw-semibold">{{ $previous->started_at->format('d/m/Y') }}</div>
                            <div class="text-muted">{{ $previous->primary_diagnosis?->description ?? $previous->reason ?? 'Sin diagnóstico' }}</div>
                        </a>
                    @empty
                        <p class="text-muted mb-0">Es su primera consulta.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<datalist id="diagnosis-suggestions">@foreach($diagnosisSuggestions as $s)<option value="{{ $s }}">@endforeach</datalist>
<datalist id="medication-suggestions">@foreach($inventoryProducts as $p)<option value="{{ $p->label }}">@endforeach @foreach($medicationSuggestions->diff($inventoryProducts->map->label) as $s)<option value="{{ $s }}">@endforeach</datalist>
<datalist id="inventory-products">@foreach($inventoryProducts as $p)<option value="{{ $p->label }}">@endforeach</datalist>

<template id="tpl-diagnoses">@include('admin.consultations.partials.diagnosis-row', ['i' => '__i__', 'row' => []])</template>
<template id="tpl-prescription">@include('admin.consultations.partials.prescription-row', ['i' => '__i__', 'row' => []])</template>
<template id="tpl-procedures">@include('admin.consultations.partials.procedure-row', ['i' => '__i__', 'row' => []])</template>
<template id="tpl-supplies">@include('admin.consultations.partials.supply-row', ['i' => '__i__', 'row' => []])</template>

<script>
(function () {
    // Filas dinámicas: se clona la plantilla con un índice nuevo.
    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            const key = button.dataset.addRow;
            const container = document.getElementById('rows-' + key);
            const index = parseInt(container.dataset.next, 10);
            container.dataset.next = index + 1;
            const html = document.getElementById('tpl-' + key).innerHTML.replaceAll('__i__', index);
            container.insertAdjacentHTML('beforeend', html);
            // El primer diagnóstico es el principal; los siguientes, secundarios.
            if (key === 'diagnoses' && container.children.length === 1) {
                container.lastElementChild.querySelector('select').value = 'primary';
            }
            container.lastElementChild.querySelector('input:not([type=hidden]), select, textarea')?.focus();
        });
    });
    document.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-remove-row]');
        if (remove) remove.closest('.dynamic-row').remove();
    });

    // Vincula el texto elegido de la lista con el producto del inventario (id oculto).
    const inventory = @json($inventoryProducts->mapWithKeys(fn ($p) => [$p->label => $p->id]));
    document.addEventListener('input', function (event) {
        const field = event.target.closest('[data-product-link]');
        if (!field) return;
        const hidden = field.closest('.dynamic-row').querySelector('[data-product-id]');
        if (hidden) hidden.value = inventory[field.value] ?? '';
    });

    // Si no hay diagnósticos, se ofrece una fila vacía de entrada.
    const diagnoses = document.getElementById('rows-diagnoses');
    if (!diagnoses.children.length) {
        document.querySelector('[data-add-row="diagnoses"]').click();
        document.activeElement?.blur();
        window.scrollTo(0, 0);
    }

    // Avisar antes de salir con cambios sin guardar.
    const form = document.getElementById('consultation-form');
    let dirty = false;
    form.addEventListener('input', () => dirty = true);
    form.addEventListener('submit', () => dirty = false);
    window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
</script>
@endsection
