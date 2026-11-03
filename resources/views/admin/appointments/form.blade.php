@php
    $defaultService = old('service_id', $appointment?->service_id);
    $duration = old('duration_minutes', $appointment?->duration_minutes ?? config('vexi.agenda.default_duration'));
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $appointment ? 'Reprogramar / editar cita' : 'Agendar cita' }}</h1>
            <p class="text-muted mb-0">Paciente, servicio, profesional y horario.</p>
        </div>
        <a href="{{ $appointment ? route('appointments.show', $appointment) : route('appointments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse"></i> Paciente</h6>
                @include('admin.appointments.partials.patient-fields', ['selectedClient' => $selectedClient, 'selectedPetId' => $selectedPetId])
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-calendar-event"></i> Cita</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Servicio</label>
                        <select name="service_id" id="service-id" class="form-select @error('service_id') is-invalid @enderror">
                            <option value="">Sin especificar</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}" data-duration="{{ $service->duration_minutes }}" @selected((string) $defaultService === (string) $service->id)>
                                    {{ $service->name }} ({{ $service->duration_minutes }} min)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profesional</label>
                        <select name="veterinarian_id" class="form-select @error('veterinarian_id') is-invalid @enderror">
                            <option value="">Sin asignar</option>
                            @foreach($professionals as $professional)
                                <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id', $appointment?->veterinarian_id) === (string) $professional->id)>
                                    {{ $professional->name }}{{ $professional->role_name ? " — {$professional->role_name}" : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" value="{{ old('date', $defaultStart->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hora <span class="text-danger">*</span></label>
                        <input type="time" name="time" step="300" class="form-control @error('time') is-invalid @enderror" value="{{ old('time', $defaultStart->format('H:i')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duración (min) <span class="text-danger">*</span></label>
                        <input type="number" name="duration_minutes" id="duration" min="5" max="1440" step="5" class="form-control @error('duration_minutes') is-invalid @enderror" value="{{ $duration }}" required>
                    </div>

                    @if($branches->count() > 1)
                        <div class="col-md-6">
                            <label class="form-label">Sucursal</label>
                            <select name="branch_id" class="form-select">
                                <option value="">—</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $appointment?->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($branches->count() === 1)
                        <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
                    @endif

                    <div class="col-12">
                        <label class="form-label">Motivo de consulta</label>
                        <input type="text" name="reason" class="form-control" value="{{ old('reason', $appointment?->reason) }}" maxlength="255" placeholder="Ej.: vómitos desde ayer, control post operatorio…">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $appointment?->notes) }}</textarea>
                    </div>
                    @unless($appointment)
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="confirmed" value="1" id="confirmed" @checked(old('confirmed'))>
                                <label class="form-check-label" for="confirmed">El cliente ya confirmó su asistencia</label>
                            </div>
                        </div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ $appointment ? 'Guardar cambios' : 'Agendar' }}</button>
            <a href="{{ $appointment ? route('appointments.show', $appointment) : route('appointments.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

<script>
    // Al elegir servicio se propone su duración.
    document.getElementById('service-id').addEventListener('change', function () {
        const minutes = this.selectedOptions[0]?.dataset.duration;
        if (minutes) document.getElementById('duration').value = minutes;
    });
</script>
