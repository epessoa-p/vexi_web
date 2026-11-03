@php
    $d = $deworming;
    $intervals = collect(\App\Models\Deworming::TYPES)->keys()->mapWithKeys(fn ($type) => [$type => \App\Models\Deworming::defaultIntervalDays($type)]);
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 52])
            <div>
                <h1 class="mb-0 h3">{{ $d ? 'Editar desparasitación' : 'Desparasitar' }} · {{ $pet->name }}</h1>
                <div class="text-muted small">
                    {{ $pet->species?->name }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}
                    @if($pet->weight) · último peso {{ rtrim(rtrim(number_format($pet->weight, 2), '0'), '.') }} kg @endif
                </div>
            </div>
        </div>
        <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'health']) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" class="card border-0 shadow-sm">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <input type="hidden" name="pet_id" value="{{ $pet->id }}">
        @if($consultationId)<input type="hidden" name="consultation_id" value="{{ $consultationId }}">@endif

        <div class="card-body p-4 row g-3">
            <div class="col-12">
                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach(\App\Models\Deworming::TYPES as $value => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="type-{{ $value }}" value="{{ $value }}" @checked(old('type', $d?->type ?? request('type', 'internal')) === $value)>
                            <label class="form-check-label" for="type-{{ $value }}">{{ $label }} <small class="text-muted">(cada {{ $intervals[$value] }} días)</small></label>
                        </div>
                    @endforeach
                </div>
            </div>
            @if($inventoryProducts->isNotEmpty())
                <div class="col-12">
                    <label class="form-label">Producto del inventario</label>
                    <select name="product_id" id="inventory-product" class="form-select @error('product_id') is-invalid @enderror">
                        <option value="">No descontar del inventario</option>
                        @foreach($inventoryProducts as $item)
                            <option value="{{ $item->id }}" data-label="{{ $item->label }}" @selected((string) old('product_id', $d?->product_id) === (string) $item->id)>{{ $item->label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Al registrar, sale una unidad del stock.</div>
                </div>
            @endif
            <div class="col-md-8">
                <label class="form-label">Producto utilizado <span class="text-danger">*</span></label>
                <input type="text" name="product" class="form-control @error('product') is-invalid @enderror" value="{{ old('product', $d?->product) }}" list="product-suggestions" maxlength="160" placeholder="Ej.: Drontal Plus, Bravecto 20–40 kg">
                <datalist id="product-suggestions">@foreach($productSuggestions as $s)<option value="{{ $s }}">@endforeach</datalist>
            </div>
            <div class="col-md-4">
                <label class="form-label">Dosis</label>
                <input type="text" name="dose" class="form-control" value="{{ old('dose', $d?->dose) }}" maxlength="60" placeholder="Ej.: 1 comp.">
            </div>
            <div class="col-md-4">
                <label class="form-label">Peso al aplicar (kg)</label>
                <input type="number" step="0.01" name="weight" class="form-control @error('weight') is-invalid @enderror" value="{{ old('weight', $d?->weight ?? $pet->weight) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha de aplicación <span class="text-danger">*</span></label>
                <input type="date" name="applied_at" id="applied-at" class="form-control @error('applied_at') is-invalid @enderror" value="{{ old('applied_at', ($d?->applied_at ?? now())->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Próxima aplicación</label>
                <input type="date" name="next_due_at" id="next-due" class="form-control @error('next_due_at') is-invalid @enderror" value="{{ old('next_due_at', $d?->next_due_at?->format('Y-m-d')) }}">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="no_next_due" value="1" id="no-next-due" @checked(old('no_next_due', $d && ! $d->next_due_at))>
                    <label class="form-check-label small" for="no-next-due">No programar</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Responsable</label>
                <select name="veterinarian_id" class="form-select @error('veterinarian_id') is-invalid @enderror">
                    <option value="">—</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) old('veterinarian_id', $d?->veterinarian_id ?? auth()->id()) === (string) $professional->id)>{{ $professional->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="notes" rows="2" class="form-control">{{ old('notes', $d?->notes) }}</textarea>
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
    const intervals = @json($intervals);
    const applied = document.getElementById('applied-at');
    const next = document.getElementById('next-due');
    const noNext = document.getElementById('no-next-due');
    const type = () => document.querySelector('input[name="type"]:checked')?.value;

    function suggest() {
        if (noNext.checked || !applied.value || !type()) return;
        const date = new Date(applied.value + 'T00:00:00Z');
        date.setUTCDate(date.getUTCDate() + intervals[type()]);
        next.value = date.toISOString().slice(0, 10);
    }

    document.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', suggest));
    // Elegido del inventario, el nombre del producto se completa solo.
    document.getElementById('inventory-product')?.addEventListener('change', function () {
        const label = this.selectedOptions[0]?.dataset.label;
        if (label) document.querySelector('input[name="product"]').value = label;
    });
    applied.addEventListener('change', suggest);
    noNext.addEventListener('change', function () {
        next.disabled = noNext.checked;
        if (noNext.checked) next.value = ''; else suggest();
    });
    next.disabled = noNext.checked;
    if (!next.value) suggest();
})();
</script>
