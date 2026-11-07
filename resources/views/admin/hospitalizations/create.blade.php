@extends('layouts.app')

@section('title', 'Ingresar paciente')

@section('page')
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Ingresar paciente</h1>
            <p class="text-muted mb-0">Admisión a hospitalización.@if($consultation) Desde la consulta del {{ $consultation->started_at->format('d/m/Y') }}.@endif</p>
        </div>
        <a href="{{ route('hospitalizations.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ route('hospitalizations.store') }}" method="POST">
        @csrf
        @if($consultation)<input type="hidden" name="consultation_id" value="{{ $consultation->id }}">@endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse"></i> Paciente</h6>
                @include('admin.appointments.partials.patient-fields', ['selectedClient' => $selectedClient, 'selectedPetId' => $selectedPetId])
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4 row g-3">
                <div class="col-md-6">
                    <label class="form-label">Motivo de hospitalización <span class="text-danger">*</span></label>
                    <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason', $consultation?->reason) }}" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Diagnóstico de ingreso</label>
                    <input type="text" name="admission_diagnosis" class="form-control" value="{{ old('admission_diagnosis', $consultation?->primary_diagnosis?->description) }}" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha y hora de ingreso <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="admitted_at" class="form-control @error('admitted_at') is-invalid @enderror" value="{{ old('admitted_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Veterinario responsable</label>
                    <select name="veterinarian_id" class="form-select @error('veterinarian_id') is-invalid @enderror">
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id', $consultation?->veterinarian_id ?? auth()->id()) === (string) $professional->id)>{{ $professional->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado del paciente</label>
                    <select name="condition" class="form-select">
                        @foreach(\App\Models\Hospitalization::CONDITIONS as $value => [$label])
                            <option value="{{ $value }}" @selected(old('condition', 'observation') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jaula o espacio</label>
                    <select name="kennel_id" class="form-select @error('kennel_id') is-invalid @enderror">
                        <option value="">Sin asignar</option>
                        @foreach($kennels as $kennel)
                            <option value="{{ $kennel->id }}" @selected((string) old('kennel_id') === (string) $kennel->id)>{{ $kennel->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Solo se listan los espacios libres.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tarifa por día ({{ \App\Support\Money::symbol($tenantCompany) }})</label>
                    <input type="number" step="0.01" min="0" name="daily_rate" class="form-control @error('daily_rate') is-invalid @enderror" value="{{ old('daily_rate', 0) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Ingresar</button></div>
            </div>
        </div>
    </form>
</div>
@endsection
