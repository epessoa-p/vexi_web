@extends('layouts.app')

@section('title', $title)

@section('page')
<div class="container-fluid" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $title }}</h1>
            <p class="text-muted mb-0">Elige el paciente.</p>
        </div>
        <a href="{{ route('health.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="GET" class="card border-0 shadow-sm">
        <div class="card-body p-4">
            @include('admin.appointments.partials.patient-fields', ['selectedClient' => $selectedClient, 'selectedPetId' => null])
            <button class="btn btn-primary mt-3" type="submit">Continuar <i class="bi bi-arrow-right"></i></button>
        </div>
    </form>
</div>
@endsection
