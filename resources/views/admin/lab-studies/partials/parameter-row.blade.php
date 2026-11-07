{{-- Fila de parámetro del catálogo. Parámetros: $i (índice o '__i__'), $row. --}}
<tr>
    <td><input type="text" name="parameters[{{ $i }}][name]" class="form-control form-control-sm" value="{{ $row['name'] ?? '' }}" maxlength="80"></td>
    <td><input type="text" name="parameters[{{ $i }}][unit]" class="form-control form-control-sm" value="{{ $row['unit'] ?? '' }}" maxlength="30"></td>
    <td><input type="number" step="any" name="parameters[{{ $i }}][reference_min]" class="form-control form-control-sm" value="{{ $row['reference_min'] ?? '' }}"></td>
    <td><input type="number" step="any" name="parameters[{{ $i }}][reference_max]" class="form-control form-control-sm" value="{{ $row['reference_max'] ?? '' }}"></td>
    <td><input type="text" name="parameters[{{ $i }}][reference_text]" class="form-control form-control-sm" value="{{ $row['reference_text'] ?? '' }}" maxlength="120" placeholder="Ej.: Negativo"></td>
    <td><button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove-row title="Quitar"><i class="bi bi-x-circle"></i></button></td>
</tr>
