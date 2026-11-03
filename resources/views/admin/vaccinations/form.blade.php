@php
    $v = $vaccination;
    $selectedVaccine = old('vaccine_id', $v?->vaccine_id ?? request('vaccine_id'));
    $protocols = $vaccines->mapWithKeys(fn ($vaccine) => [$vaccine->id => [
        'doses' => $vaccine->doses_in_series,
        'interval' => $vaccine->series_interval_days,
        'booster' => $vaccine->booster_interval_days,
        'label' => $vaccine->protocol_label,
        'last' => $lastDoses[$vaccine->id] ?? 0,
    ]]);
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 52])
            <div>
                <h1 class="mb-0 h3">{{ $v ? 'Editar vacuna' : 'Aplicar vacuna' }} · {{ $pet->name }}</h1>
                <div class="text-muted small">{{ $pet->species?->name }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }} · {{ $pet->client?->full_name }}</div>
            </div>
        </div>
        <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'health']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($vaccines->isEmpty())
        <div class="alert alert-warning">No hay vacunas activas para {{ mb_strtolower($pet->species?->name ?? 'esta especie') }}. Agrégalas en <a href="{{ route('vaccines.index') }}">Configuración › Vacunas</a>.</div>
    @endif

    <form action="{{ $action }}" method="POST" class="card border-0 shadow-sm">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <input type="hidden" name="pet_id" value="{{ $pet->id }}">
        @if($consultationId)<input type="hidden" name="consultation_id" value="{{ $consultationId }}">@endif

        <div class="card-body p-4 row g-3">
            <div class="col-md-8">
                <label class="form-label">Vacuna <span class="text-danger">*</span></label>
                <select name="vaccine_id" id="vaccine-id" class="form-select @error('vaccine_id') is-invalid @enderror" required>
                    <option value="">Seleccionar…</option>
                    @foreach($vaccines as $vaccine)
                        <option value="{{ $vaccine->id }}" @selected((string) $selectedVaccine === (string) $vaccine->id)>{{ $vaccine->name }}</option>
                    @endforeach
                </select>
                <div class="form-text" id="protocol-hint"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Nº de dosis <span class="text-danger">*</span></label>
                <input type="number" name="dose_number" id="dose-number" min="1" max="50" class="form-control @error('dose_number') is-invalid @enderror" value="{{ old('dose_number', $v?->dose_number ?? 1) }}" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Fecha de aplicación <span class="text-danger">*</span></label>
                <input type="date" name="applied_at" id="applied-at" class="form-control @error('applied_at') is-invalid @enderror" value="{{ old('applied_at', ($v?->applied_at ?? now())->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Lote</label>
                <input type="text" name="lot" class="form-control @error('lot') is-invalid @enderror" value="{{ old('lot', $v?->lot) }}" maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="form-label">Vencimiento del lote</label>
                <input type="date" name="lot_expires_at" class="form-control @error('lot_expires_at') is-invalid @enderror" value="{{ old('lot_expires_at', $v?->lot_expires_at?->format('Y-m-d')) }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Veterinario responsable</label>
                <select name="veterinarian_id" class="form-select @error('veterinarian_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id', $v?->veterinarian_id ?? auth()->id()) === (string) $professional->id)>{{ $professional->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Próxima dosis</label>
                <input type="date" name="next_due_at" id="next-due" class="form-control @error('next_due_at') is-invalid @enderror" value="{{ old('next_due_at', $v?->next_due_at?->format('Y-m-d')) }}">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="no_next_due" value="1" id="no-next-due" @checked(old('no_next_due', $v && ! $v->next_due_at))>
                    <label class="form-check-label small" for="no-next-due">No programar otra dosis</label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Reacciones, sitio de aplicación…">{{ old('notes', $v?->notes) }}</textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'health']) }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    // Propone dosis y próxima fecha según el protocolo de la vacuna.
    const protocols = @json($protocols);
    const editing = @json((bool) $v);
    const vaccine = document.getElementById('vaccine-id');
    const dose = document.getElementById('dose-number');
    const applied = document.getElementById('applied-at');
    const next = document.getElementById('next-due');
    const noNext = document.getElementById('no-next-due');
    const hint = document.getElementById('protocol-hint');

    function addDays(value, days) {
        const date = new Date(value + 'T00:00:00Z');
        date.setUTCDate(date.getUTCDate() + days);
        return date.toISOString().slice(0, 10);
    }

    function suggestNext() {
        const p = protocols[vaccine.value];
        if (!p || noNext.checked || !applied.value) return;
        const n = parseInt(dose.value || '1', 10);
        const days = n < p.doses ? p.interval : p.booster;
        next.value = days ? addDays(applied.value, days) : '';
    }

    vaccine.addEventListener('change', function () {
        const p = protocols[vaccine.value];
        hint.textContent = p ? 'Protocolo: ' + p.label + (p.last ? ' · última dosis registrada: ' + p.last : '') : '';
        if (p && !editing) dose.value = p.last + 1;
        suggestNext();
    });
    [dose, applied].forEach((field) => field.addEventListener('change', suggestNext));
    noNext.addEventListener('change', function () {
        next.disabled = noNext.checked;
        if (noNext.checked) next.value = ''; else suggestNext();
    });

    next.disabled = noNext.checked;
    if (vaccine.value) {
        const p = protocols[vaccine.value];
        hint.textContent = p ? 'Protocolo: ' + p.label + (p.last ? ' · última dosis registrada: ' + p.last : '') : '';
        // Vacuna preseleccionada (desde el calendario): proponer la dosis siguiente.
        if (p && !editing && @json(old('dose_number') === null)) dose.value = p.last + 1;
        if (!next.value) suggestNext();
    }
})();
</script>
