@extends('layouts.app')

@section('title', 'Solicitar estudio')

@section('page')
@php
    $categories = \App\Models\LabStudyType::CATEGORIES;
    $selectedStudies = collect(old('study_types', []))->map(fn ($id) => (int) $id);
    $selectedConsultation = old('consultation_id', $consultationId);
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">Solicitar estudio</h1>
            <p class="text-muted mb-0">Se crea una solicitud por cada estudio elegido, con su propio seguimiento.</p>
        </div>
        <a href="{{ route('lab.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ route('lab.store') }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse"></i> Paciente</h6>
                @include('admin.appointments.partials.patient-fields', ['selectedClient' => $selectedClient, 'selectedPetId' => $selectedPetId])
                <div class="mt-3">
                    <label class="form-label">Consulta relacionada</label>
                    <select name="consultation_id" id="consultation-id" class="form-select @error('consultation_id') is-invalid @enderror">
                        <option value="">Ninguna</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-clipboard2-data"></i> Estudios <span class="text-danger">*</span></h6>
                @error('study_types')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror
                <div class="row g-3">
                    @forelse($studyTypes as $category => $studies)
                        <div class="col-md-4">
                            <div class="small text-muted fw-semibold mb-1">{{ $categories[$category] ?? $category }}</div>
                            @foreach($studies as $study)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="study_types[]" value="{{ $study->id }}" id="study-{{ $study->id }}" @checked($selectedStudies->contains($study->id))>
                                    <label class="form-check-label" for="study-{{ $study->id }}">{{ $study->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="col-12 text-muted">No hay estudios activos en el catálogo.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4 row g-3">
                <div class="col-md-4">
                    <label class="form-label">Veterinario solicitante</label>
                    <select name="requested_by" class="form-select @error('requested_by') is-invalid @enderror">
                        @foreach($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected((string) old('requested_by', auth()->id()) === (string) $professional->id)>{{ $professional->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha de solicitud <span class="text-danger">*</span></label>
                    <input type="date" name="requested_at" class="form-control @error('requested_at') is-invalid @enderror" value="{{ old('requested_at', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Laboratorio externo</label>
                    <input type="text" name="external_lab" class="form-control" value="{{ old('external_lab') }}" maxlength="120" placeholder="Si se deriva">
                </div>
                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="Sospecha clínica, ayuno, zona a estudiar…">{{ old('notes') }}</textarea>
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Solicitar</button></div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const url = @json(route('lab.pet-consultations', ['pet' => '__ID__']));
    const pet = document.getElementById('client-pet');
    const select = document.getElementById('consultation-id');
    let preselect = @json($selectedConsultation ? (string) $selectedConsultation : null);

    async function load() {
        select.innerHTML = '<option value="">Ninguna</option>';
        if (!pet.value) return;
        const response = await fetch(url.replace('__ID__', pet.value), { headers: { 'Accept': 'application/json' } });
        const consultations = response.ok ? await response.json() : [];
        consultations.forEach((c) => select.add(new Option(c.label, c.id)));
        if (preselect) select.value = preselect;
        preselect = null;
    }

    // El selector de mascota dispara `change` también al llenarse.
    pet.addEventListener('change', load);
})();
</script>
@endsection
