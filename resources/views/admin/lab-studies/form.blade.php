@php
    $s = $studyType;
    $rows = old('parameters', $parameters);
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $s ? "Editar {$s->name}" : 'Nuevo estudio' }}</h1>
            <p class="text-muted mb-0">Los cambios se aplican a solicitudes nuevas; los informes emitidos conservan su referencia.</p>
        </div>
        <a href="{{ route('lab-studies.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4 row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $s?->name) }}" maxlength="120" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoría <span class="text-danger">*</span></label>
                    <select name="category" class="form-select">
                        @foreach(\App\Models\LabStudyType::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" @selected(old('category', $s?->category ?? 'other') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description', $s?->description) }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Precio ({{ \App\Support\Money::symbol($tenantCompany) }})</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price', $s?->price ?? 0) }}">
                    <div class="form-text">Es el precio que propone el punto de venta.</div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $s?->active ?? true))>
                        <label class="form-check-label" for="active">Activo</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">Parámetros</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="add-parameter"><i class="bi bi-plus-lg"></i> Agregar</button>
                </div>
                <p class="small text-muted">Con mínimo o máximo, el resultado se marca como alto o bajo según la referencia. Un estudio de imagen puede no tener parámetros.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Parámetro</th><th style="width: 8rem">Unidad</th><th style="width: 7rem">Ref. mín.</th><th style="width: 7rem">Ref. máx.</th><th>Ref. texto</th><th></th></tr></thead>
                        <tbody id="parameter-rows" data-next="{{ count($rows) }}">
                            @foreach($rows as $i => $row)
                                @include('admin.lab-studies.partials.parameter-row', ['i' => $i, 'row' => $row])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="{{ route('lab-studies.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

<template id="parameter-template">@include('admin.lab-studies.partials.parameter-row', ['i' => '__i__', 'row' => []])</template>
<script>
(function () {
    const rows = document.getElementById('parameter-rows');
    document.getElementById('add-parameter').addEventListener('click', function () {
        const index = parseInt(rows.dataset.next, 10);
        rows.dataset.next = index + 1;
        rows.insertAdjacentHTML('beforeend', document.getElementById('parameter-template').innerHTML.replaceAll('__i__', index));
        rows.lastElementChild.querySelector('input').focus();
    });
    rows.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-remove-row]');
        if (remove) remove.closest('tr').remove();
    });
})();
</script>
