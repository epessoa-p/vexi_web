@php
    $selectedSpecies = collect(old('species', $vaccine?->species->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id);
@endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $vaccine ? 'Editar vacuna' : 'Nueva vacuna' }}</h1>
            <p class="text-muted mb-0">El protocolo calcula la próxima dosis al registrar cada aplicación.</p>
        </div>
        <a href="{{ route('vaccines.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" class="card border-0 shadow-sm">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <div class="card-body p-4 row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $vaccine?->name) }}" maxlength="120" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fabricante</label>
                <input type="text" name="manufacturer" class="form-control" value="{{ old('manufacturer', $vaccine?->manufacturer) }}" maxlength="120">
            </div>
            <div class="col-md-3">
                <label class="form-label">Presentación</label>
                <input type="text" name="presentation" class="form-control" value="{{ old('presentation', $vaccine?->presentation) }}" maxlength="120" placeholder="Ej.: frasco 1 ml">
            </div>

            <div class="col-12">
                <label class="form-label">Especies a las que aplica</label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($species as $item)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="species[]" value="{{ $item->id }}" id="species-{{ $item->id }}" @checked($selectedSpecies->contains($item->id))>
                            <label class="form-check-label" for="species-{{ $item->id }}">{{ $item->name }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">Sin ninguna marcada, se ofrece para todas las especies.</div>
            </div>

            @if($products->isNotEmpty())
                <div class="col-12">
                    <label class="form-label">Producto del inventario</label>
                    <select name="product_id" class="form-select @error('product_id') is-invalid @enderror">
                        <option value="">Sin vincular (no descuenta stock)</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('product_id', $vaccine?->product_id) === (string) $product->id)>{{ $product->label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Al aplicar la vacuna se descuenta una dosis de este producto (del lote anotado, o del que vence antes).</div>
                </div>
            @endif

            <div class="col-12"><hr class="my-1"><h6 class="fw-bold mb-0">Protocolo recomendado</h6></div>
            <div class="col-md-3">
                <label class="form-label">Dosis de la serie inicial <span class="text-danger">*</span></label>
                <input type="number" name="doses_in_series" min="1" max="10" class="form-control @error('doses_in_series') is-invalid @enderror" value="{{ old('doses_in_series', $vaccine?->doses_in_series ?? 1) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Días entre dosis</label>
                <input type="number" name="series_interval_days" min="1" max="365" class="form-control @error('series_interval_days') is-invalid @enderror" value="{{ old('series_interval_days', $vaccine?->series_interval_days) }}" placeholder="Ej.: 21">
            </div>
            <div class="col-md-3">
                <label class="form-label">Refuerzo cada (días)</label>
                <input type="number" name="booster_interval_days" min="1" max="3650" class="form-control @error('booster_interval_days') is-invalid @enderror" value="{{ old('booster_interval_days', $vaccine?->booster_interval_days) }}" placeholder="365 = anual">
            </div>
            <div class="col-md-3">
                <label class="form-label">Edad mínima (semanas)</label>
                <input type="number" name="min_age_weeks" min="0" max="520" class="form-control" value="{{ old('min_age_weeks', $vaccine?->min_age_weeks) }}">
            </div>

            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="description" rows="2" class="form-control" placeholder="Enfermedades que previene, notas del protocolo…">{{ old('description', $vaccine?->description) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $vaccine?->active ?? true))>
                    <label class="form-check-label" for="active">Activa</label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ route('vaccines.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>
