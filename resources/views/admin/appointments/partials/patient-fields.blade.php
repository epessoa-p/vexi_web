{{--
    Propietario (autocompletado) + mascota (select que se llena con las
    mascotas de ese propietario). Parámetros: $selectedClient, $selectedPetId, $prefix.
--}}
@php
    $prefix = $prefix ?? 'client';
    $oldClientId = old('client_id');
    if ($oldClientId && (string) $selectedClient?->id !== (string) $oldClientId) {
        $selectedClient = \App\Models\Client::find($oldClientId);
    }
    $selectedPetId = old('pet_id', $selectedPetId ?? null);
@endphp
<div class="row g-3">
    <div class="col-md-7">
        <label class="form-label">Propietario <span class="text-danger">*</span></label>
        @include('admin.clients.partials.picker', ['selectedClient' => $selectedClient, 'prefix' => $prefix])
    </div>
    <div class="col-md-5">
        <label class="form-label">Mascota <span class="text-danger">*</span></label>
        <select name="pet_id" id="{{ $prefix }}-pet" class="form-select @error('pet_id') is-invalid @enderror" required>
            <option value="">{{ $selectedClient ? 'Cargando…' : 'Elige primero el propietario' }}</option>
        </select>
        <div class="form-text text-danger d-none" id="{{ $prefix }}-pet-warning"><i class="bi bi-exclamation-triangle-fill"></i> Tiene alertas clínicas: revisa su ficha.</div>
    </div>
</div>

<script>
(function () {
    const prefix = @json($prefix);
    const petsUrl = @json(route('clients.pets', ['client' => '__ID__']));
    const select = document.getElementById(prefix + '-pet');
    const warning = document.getElementById(prefix + '-pet-warning');
    let preselect = @json($selectedPetId ? (string) $selectedPetId : null);

    async function loadPets(clientId) {
        select.innerHTML = '<option value="">Cargando…</option>';
        const response = await fetch(petsUrl.replace('__ID__', clientId), { headers: { 'Accept': 'application/json' } });
        const pets = response.ok ? await response.json() : [];
        select.innerHTML = '';
        if (!pets.length) {
            select.innerHTML = '<option value="">Este propietario no tiene mascotas activas</option>';
            return;
        }
        if (pets.length > 1) select.add(new Option('Seleccionar…', ''));
        for (const pet of pets) {
            const option = new Option(pet.name + (pet.detail ? ' — ' + pet.detail : '') + (pet.warning ? ' ⚠' : ''), pet.id);
            option.dataset.warning = pet.warning ? '1' : '';
            select.add(option);
        }
        if (preselect) select.value = preselect;
        preselect = null;
        select.dispatchEvent(new Event('change'));
    }

    select.addEventListener('change', function () {
        const option = select.selectedOptions[0];
        warning.classList.toggle('d-none', !(option && option.dataset.warning));
    });

    document.addEventListener(prefix + ':selected', (event) => loadPets(event.detail.id));
    document.addEventListener(prefix + ':cleared', function () {
        select.innerHTML = '<option value="">Elige primero el propietario</option>';
        warning.classList.add('d-none');
    });

    const current = document.getElementById(prefix + '-id').value;
    if (current) loadPets(current);
})();
</script>
