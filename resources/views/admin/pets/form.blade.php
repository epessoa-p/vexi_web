@php
    // Si la validación falló, el propietario elegido llega por old() y no por el controlador.
    $oldClientId = old('client_id');
    if ($oldClientId && (string) $selectedClient?->id !== (string) $oldClientId) {
        $selectedClient = \App\Models\Client::find($oldClientId);
    }
    $selectedSpecies = old('species_id', $pet?->species_id);
    $selectedBreed = old('breed_id', $pet?->breed_id);
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $pet ? "Editar a {$pet->name}" : 'Nueva mascota' }}</h1>
            <p class="text-muted mb-0">Datos generales del paciente. Los antecedentes médicos se registran en su ficha.</p>
        </div>
        <a href="{{ $pet ? route('pets.show', $pet) : ($selectedClient ? route('clients.show', $selectedClient) : route('pets.index')) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="row g-4">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="col-lg-8">
            {{-- Propietario --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Propietario <span class="text-danger">*</span></h6>
                    @include('admin.clients.partials.picker', ['selectedClient' => $selectedClient])
                </div>
            </div>

            {{-- Datos generales --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-heart-pulse"></i> Datos de la mascota</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $pet?->name) }}" required maxlength="80">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-select">
                                @foreach(\App\Models\Pet::STATUSES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $pet?->status ?? 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Especie <span class="text-danger">*</span></label>
                            <select name="species_id" id="species-id" class="form-select @error('species_id') is-invalid @enderror" required>
                                <option value="">Seleccionar…</option>
                                @foreach($species as $item)
                                    <option value="{{ $item->id }}" @selected((string) $selectedSpecies === (string) $item->id)>{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Raza</label>
                            <select name="breed_id" id="breed-id" class="form-select @error('breed_id') is-invalid @enderror">
                                <option value="">Sin raza / no aplica</option>
                                @foreach($species as $item)
                                    @foreach($item->breeds as $breed)
                                        <option value="{{ $breed->id }}" data-species="{{ $item->id }}" @selected((string) $selectedBreed === (string) $breed->id)>{{ $breed->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 pt-1">
                                @foreach(\App\Models\Pet::SEXES as $value => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="sex" id="sex-{{ $value }}" value="{{ $value }}" @checked(old('sex', $pet?->sex ?? 'unknown') === $value)>
                                        <label class="form-check-label" for="sex-{{ $value }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="sterilized" value="1" id="sterilized" @checked(old('sterilized', $pet?->sterilized))>
                                <label class="form-check-label" for="sterilized">Esterilizado / castrado</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="birth_date" id="birth-date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $pet?->birth_date?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">¿No la sabe? Edad aproximada</label>
                            <div class="input-group">
                                <input type="number" min="0" max="40" id="age-years" class="form-control" placeholder="Años">
                                <input type="number" min="0" max="11" id="age-months" class="form-control" placeholder="Meses">
                                <button type="button" class="btn btn-outline-secondary" id="age-apply">Calcular</button>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Peso actual (kg)</label>
                            <input type="number" step="0.01" min="0.01" name="weight" class="form-control @error('weight') is-invalid @enderror" value="{{ old('weight', $pet?->weight) }}">
                            @if($pet)<div class="form-text">Si cambia, se agrega al historial de peso.</div>@endif
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tamaño</label>
                            <select name="size" class="form-select">
                                <option value="">—</option>
                                @foreach(\App\Models\Pet::SIZES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('size', $pet?->size) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color / pelaje</label>
                            <input type="text" name="color" class="form-control" value="{{ old('color', $pet?->color) }}" maxlength="80">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Nº de microchip</label>
                            <input type="text" name="microchip" class="form-control @error('microchip') is-invalid @enderror" value="{{ old('microchip', $pet?->microchip) }}" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nº de identificación</label>
                            <input type="text" name="identification" class="form-control" value="{{ old('identification', $pet?->identification) }}" maxlength="30" placeholder="Placa, tatuaje, registro…">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observaciones generales</label>
                            <textarea name="notes" rows="3" class="form-control">{{ old('notes', $pet?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Foto --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <h6 class="fw-bold mb-3 text-start"><i class="bi bi-camera"></i> Fotografía</h6>
                    <img id="photo-preview" src="{{ $pet?->photo_url }}" alt="" class="rounded object-fit-cover mb-3 border {{ $pet?->photo_url ? '' : 'd-none' }}" style="width: 100%; max-width: 260px; aspect-ratio: 1;">
                    <div id="photo-empty" class="rounded bg-light border d-flex align-items-center justify-content-center mb-3 mx-auto text-muted {{ $pet?->photo_url ? 'd-none' : '' }}" style="width: 100%; max-width: 260px; aspect-ratio: 1;">
                        <i class="bi bi-image fs-1"></i>
                    </div>
                    <input type="file" name="photo" id="photo-input" accept="image/*" class="form-control @error('photo') is-invalid @enderror">
                    <div class="form-text">JPG o PNG, hasta 4 MB.</div>
                    @if($pet?->photo)
                        <div class="form-check text-start mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove-photo">
                            <label class="form-check-label" for="remove-photo">Quitar la foto actual</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="{{ $pet ? route('pets.show', $pet) : route('pets.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

<script>
(function () {
    // Raza: solo las de la especie elegida.
    const species = document.getElementById('species-id');
    const breed = document.getElementById('breed-id');
    function filterBreeds() {
        for (const option of breed.options) {
            if (!option.dataset.species) continue;
            const visible = option.dataset.species === species.value;
            option.hidden = !visible;
            option.disabled = !visible;
            if (!visible && option.selected) breed.value = '';
        }
    }
    species.addEventListener('change', filterBreeds);
    filterBreeds();

    // Edad aproximada → fecha de nacimiento.
    document.getElementById('age-apply').addEventListener('click', function () {
        const years = parseInt(document.getElementById('age-years').value || '0', 10);
        const months = parseInt(document.getElementById('age-months').value || '0', 10);
        if (!years && !months) return;
        const date = new Date();
        date.setMonth(date.getMonth() - (years * 12 + months));
        document.getElementById('birth-date').value = date.toISOString().slice(0, 10);
    });

    // Vista previa de la foto.
    document.getElementById('photo-input').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const preview = document.getElementById('photo-preview');
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('d-none');
        document.getElementById('photo-empty').classList.add('d-none');
    });
})();
</script>
