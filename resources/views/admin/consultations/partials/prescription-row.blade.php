{{-- Fila de medicamento de la receta. Parámetros: $i, $row. --}}
<div class="dynamic-row border rounded p-2 mb-2 bg-light-subtle">
    <div class="row g-2">
        <div class="col-md-5">
            <input type="text" name="prescription[{{ $i }}][medication]" class="form-control form-control-sm" value="{{ $row['medication'] ?? '' }}" placeholder="Medicamento" list="medication-suggestions" maxlength="255" data-product-link autocomplete="off">
            <input type="hidden" name="prescription[{{ $i }}][product_id]" value="{{ $row['product_id'] ?? '' }}" data-product-id>
        </div>
        <div class="col-md-4">
            <input type="text" name="prescription[{{ $i }}][presentation]" class="form-control form-control-sm" value="{{ $row['presentation'] ?? '' }}" placeholder="Presentación (ej. comp. 250 mg)" maxlength="255">
        </div>
        <div class="col-md-2">
            <input type="text" name="prescription[{{ $i }}][quantity]" class="form-control form-control-sm" value="{{ $row['quantity'] ?? '' }}" placeholder="Cantidad" maxlength="40">
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="Quitar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="col-md-3">
            <input type="text" name="prescription[{{ $i }}][dose]" class="form-control form-control-sm" value="{{ $row['dose'] ?? '' }}" placeholder="Dosis (ej. 1 comp.)" maxlength="60">
        </div>
        <div class="col-md-3">
            <select name="prescription[{{ $i }}][route]" class="form-select form-select-sm">
                @foreach(\App\Models\PrescriptionItem::ROUTES as $value => $label)
                    <option value="{{ $value }}" @selected(($row['route'] ?? 'oral') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="prescription[{{ $i }}][frequency]" class="form-control form-control-sm" value="{{ $row['frequency'] ?? '' }}" placeholder="Frecuencia (ej. cada 12 h)" maxlength="60">
        </div>
        <div class="col-md-3">
            <input type="text" name="prescription[{{ $i }}][duration]" class="form-control form-control-sm" value="{{ $row['duration'] ?? '' }}" placeholder="Duración (ej. 7 días)" maxlength="60">
        </div>
        <div class="col-12">
            <input type="text" name="prescription[{{ $i }}][instructions]" class="form-control form-control-sm" value="{{ $row['instructions'] ?? '' }}" placeholder="Indicación específica (ej. con comida)" maxlength="255">
        </div>
    </div>
</div>
