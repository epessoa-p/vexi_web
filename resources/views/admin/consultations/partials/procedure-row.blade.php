{{-- Fila de procedimiento. Parámetros: $i, $row. Usa $professionals de la vista. --}}
<div class="dynamic-row border rounded p-2 mb-2 bg-light-subtle">
    <div class="row g-2">
        <div class="col-md-5">
            <input type="text" name="procedures[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $row['name'] ?? '' }}" placeholder="Procedimiento (ej. limpieza de herida)" maxlength="255">
        </div>
        <div class="col-md-4">
            <select name="procedures[{{ $i }}][performed_by]" class="form-select form-select-sm" title="Responsable">
                <option value="">Responsable…</option>
                @foreach($professionals as $professional)
                    <option value="{{ $professional->id }}" @selected((string) ($row['performed_by'] ?? '') === (string) $professional->id)>{{ $professional->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" step="0.01" min="0" name="procedures[{{ $i }}][cost]" class="form-control form-control-sm" value="{{ $row['cost'] ?? '' }}" placeholder="Costo">
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="Quitar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="col-12">
            <textarea name="procedures[{{ $i }}][description]" rows="1" class="form-control form-control-sm" placeholder="Descripción">{{ $row['description'] ?? '' }}</textarea>
        </div>
        <div class="col-md-6">
            <input type="text" name="procedures[{{ $i }}][materials]" class="form-control form-control-sm" value="{{ $row['materials'] ?? '' }}" placeholder="Materiales utilizados">
        </div>
        <div class="col-md-6">
            <input type="text" name="procedures[{{ $i }}][medications]" class="form-control form-control-sm" value="{{ $row['medications'] ?? '' }}" placeholder="Medicamentos utilizados">
        </div>
    </div>
</div>
