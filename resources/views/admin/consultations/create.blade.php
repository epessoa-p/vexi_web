@extends('layouts.app')

@section('title', 'Nueva consulta')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Nueva consulta</h1>
            <p class="text-muted mb-0">Para un paciente sin cita. Si viene de la agenda, iníciala desde la cita o la sala de espera.</p>
        </div>
        <a href="{{ route('consultations.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ route('consultations.store') }}" method="POST" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body p-4">
            @include('admin.appointments.partials.patient-fields', ['selectedClient' => $selectedClient, 'selectedPetId' => $selectedPetId])
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">Profesional</label>
                    <select name="veterinarian_id" class="form-select">
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id', auth()->id()) === (string) $professional->id)>
                                {{ $professional->name }}{{ $professional->role_name ? " — {$professional->role_name}" : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Motivo de consulta</label>
                    <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" maxlength="255">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-play-fill"></i> Iniciar consulta</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
