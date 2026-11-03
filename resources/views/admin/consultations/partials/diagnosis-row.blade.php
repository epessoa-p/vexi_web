{{-- Fila de diagnóstico. Parámetros: $i (índice o '__i__' en la plantilla), $row. --}}
<div class="dynamic-row row g-2 align-items-start mb-2">
    <div class="col-md-3">
        <select name="diagnoses[{{ $i }}][type]" class="form-select form-select-sm">
            @foreach(\App\Models\ConsultationDiagnosis::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(($row['type'] ?? ($i === 0 ? 'primary' : 'secondary')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <input type="text" name="diagnoses[{{ $i }}][code]" class="form-control form-control-sm" value="{{ $row['code'] ?? '' }}" placeholder="Código" maxlength="20">
    </div>
    <div class="col-md-6">
        <input type="text" name="diagnoses[{{ $i }}][description]" class="form-control form-control-sm" value="{{ $row['description'] ?? '' }}" placeholder="Diagnóstico" list="diagnosis-suggestions" maxlength="255">
        <input type="text" name="diagnoses[{{ $i }}][notes]" class="form-control form-control-sm mt-1" value="{{ $row['notes'] ?? '' }}" placeholder="Observaciones (opcional)" maxlength="255">
    </div>
    <div class="col-md-1 text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="Quitar"><i class="bi bi-x-lg"></i></button>
    </div>
</div>
