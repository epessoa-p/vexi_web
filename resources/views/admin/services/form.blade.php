@php
    $categories = \App\Models\Service::CATEGORIES;
    $category = old('category', $service?->category ?? 'consulta');
    $customColor = old('custom_color', (bool) $service?->color);
@endphp
<div class="container-fluid" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $service ? 'Editar servicio' : 'Nuevo servicio' }}</h1>
            <p class="text-muted mb-0">La duración se propone al agendar; el precio lo usará el punto de venta.</p>
        </div>
        <a href="{{ route('services.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" class="card border-0 shadow-sm">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <div class="card-body p-4 row g-3">
            <div class="col-md-7">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $service?->name) }}" maxlength="120" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Categoría <span class="text-danger">*</span></label>
                <select name="category" id="category" class="form-select">
                    @foreach($categories as $value => [$label, $color])
                        <option value="{{ $value }}" data-color="{{ $color }}" @selected($category === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Precio ({{ \App\Support\Money::symbol($tenantCompany) }}) <span class="text-danger">*</span></label>
                <input type="number" name="price" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $service?->price ?? 0) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Duración (min) <span class="text-danger">*</span></label>
                <input type="number" name="duration_minutes" step="5" min="5" max="1440" class="form-control @error('duration_minutes') is-invalid @enderror" value="{{ old('duration_minutes', $service?->duration_minutes ?? 30) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Color en la agenda</label>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" name="color" id="color" class="form-control form-control-color" value="{{ old('color', $service?->color ?? $categories[$category][1]) }}" {{ $customColor ? '' : 'disabled' }}>
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" name="custom_color" value="1" id="custom-color" @checked($customColor)>
                        <label class="form-check-label small" for="custom-color">Color propio</label>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="description" rows="3" class="form-control">{{ old('description', $service?->description) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $service?->active ?? true))>
                    <label class="form-check-label" for="active">Activo (aparece al agendar)</label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('services.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const color = document.getElementById('color');
    const custom = document.getElementById('custom-color');
    const category = document.getElementById('category');
    // Sin color propio, se muestra el de la categoría.
    category.addEventListener('change', function () {
        if (!custom.checked) color.value = this.selectedOptions[0].dataset.color;
    });
    custom.addEventListener('change', function () {
        color.disabled = !this.checked;
        if (!this.checked) color.value = category.selectedOptions[0].dataset.color;
    });
})();
</script>
