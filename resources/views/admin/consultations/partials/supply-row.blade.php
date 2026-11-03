{{-- Fila de insumo usado (sale del inventario al finalizar la consulta). Parámetros: $i, $row. --}}
<div class="dynamic-row row g-2 align-items-start mb-2">
    <div class="col-md-6">
        <input type="text" name="supplies[{{ $i }}][product_label]" class="form-control form-control-sm" value="{{ $row['product_label'] ?? '' }}"
               placeholder="Producto del inventario" list="inventory-products" data-product-link autocomplete="off">
        <input type="hidden" name="supplies[{{ $i }}][product_id]" value="{{ $row['product_id'] ?? '' }}" data-product-id>
    </div>
    <div class="col-md-2">
        <input type="number" step="0.01" min="0.01" name="supplies[{{ $i }}][quantity]" class="form-control form-control-sm" value="{{ $row['quantity'] ?? 1 }}" placeholder="Cant.">
    </div>
    <div class="col-md-3">
        <input type="text" name="supplies[{{ $i }}][notes]" class="form-control form-control-sm" value="{{ $row['notes'] ?? '' }}" placeholder="Uso (opcional)" maxlength="255">
    </div>
    <div class="col-md-1 text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="Quitar"><i class="bi bi-x-lg"></i></button>
    </div>
</div>
