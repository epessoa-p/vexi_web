@php $p = $product; @endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $p ? "Editar {$p->name}" : 'Nuevo producto' }}</h1>
            <p class="text-muted mb-0">La existencia cambia solo con movimientos de inventario, que quedan en el kardex.</p>
        </div>
        <a href="{{ $p ? route('products.show', $p) : route('products.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" enctype="multipart/form-data" class="row g-4">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4 row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nombre comercial <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $p?->name) }}" maxlength="160" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Categoría</label>
                        <select name="product_category_id" class="form-select">
                            <option value="">—</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('product_category_id', $p?->product_category_id) === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Marca / laboratorio</label>
                        <input type="text" name="brand" class="form-control" value="{{ old('brand', $p?->brand) }}" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Presentación</label>
                        <input type="text" name="presentation" class="form-control" value="{{ old('presentation', $p?->presentation) }}" maxlength="120" placeholder="Ej.: caja x 10 comp. 250 mg">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Unidad de medida <span class="text-danger">*</span></label>
                        <select name="unit" class="form-select">
                            @foreach(\App\Models\Product::UNITS as $value => $label)
                                <option value="{{ $value }}" @selected(old('unit', $p?->unit ?? 'unidad') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Código interno</label>
                        <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $p?->code) }}" maxlength="40">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Código de barras</label>
                        <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror" value="{{ old('barcode', $p?->barcode) }}" maxlength="60">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $p?->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4 row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Precio de compra ({{ \App\Support\Money::symbol($tenantCompany) }}) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="purchase_price" class="form-control @error('purchase_price') is-invalid @enderror" value="{{ old('purchase_price', $p?->purchase_price ?? 0) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Precio de venta ({{ \App\Support\Money::symbol($tenantCompany) }}) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="sale_price" class="form-control @error('sale_price') is-invalid @enderror" value="{{ old('sale_price', $p?->sale_price ?? 0) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock mínimo <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="min_stock" class="form-control @error('min_stock') is-invalid @enderror" value="{{ old('min_stock', $p?->min_stock ?? 0) }}" required>
                        <div class="form-text">Al llegar a este nivel se avisa. 0 = sin aviso.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="track_batches" value="1" id="track-batches" @checked(old('track_batches', $p?->track_batches))>
                            <label class="form-check-label" for="track-batches">Controlar lotes y vencimientos <small class="text-muted">(medicamentos, vacunas, alimentos)</small></label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $p?->active ?? true))>
                            <label class="form-check-label" for="active">Activo</label>
                        </div>
                    </div>
                </div>
            </div>

            @unless($p)
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 row g-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-box-arrow-in-down"></i> Existencia inicial <small class="text-muted fw-normal">(opcional)</small></h6>
                        <div class="col-md-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="0.01" min="0" name="initial_quantity" class="form-control @error('initial_quantity') is-invalid @enderror" value="{{ old('initial_quantity') }}">
                        </div>
                        @if($branches->count() > 1)
                            <div class="col-md-3">
                                <label class="form-label">Sucursal</label>
                                <select name="initial_branch_id" class="form-select">
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('initial_branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-md-3 batch-field">
                            <label class="form-label">Lote</label>
                            <input type="text" name="initial_lot" class="form-control @error('lot') is-invalid @enderror" value="{{ old('initial_lot') }}" maxlength="60">
                        </div>
                        <div class="col-md-3 batch-field">
                            <label class="form-label">Vencimiento</label>
                            <input type="date" name="initial_expires_at" class="form-control" value="{{ old('initial_expires_at') }}">
                        </div>
                    </div>
                </div>
            @endunless
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <h6 class="fw-bold mb-3 text-start"><i class="bi bi-image"></i> Imagen</h6>
                    <img id="image-preview" src="{{ $p?->image_url }}" alt="" class="rounded border object-fit-cover mb-3 {{ $p?->image_url ? '' : 'd-none' }}" style="width: 100%; max-width: 240px; aspect-ratio: 1;">
                    <input type="file" name="image" id="image-input" accept="image/*" class="form-control @error('image') is-invalid @enderror">
                    @if($p?->image)
                        <div class="form-check text-start mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove-image">
                            <label class="form-check-label" for="remove-image">Quitar la imagen</label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
            <a href="{{ $p ? route('products.show', $p) : route('products.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

<script>
(function () {
    const track = document.getElementById('track-batches');
    const toggle = () => document.querySelectorAll('.batch-field').forEach((el) => el.classList.toggle('d-none', !track.checked));
    track.addEventListener('change', toggle);
    toggle();

    document.getElementById('image-input').addEventListener('change', function () {
        if (!this.files[0]) return;
        const preview = document.getElementById('image-preview');
        preview.src = URL.createObjectURL(this.files[0]);
        preview.classList.remove('d-none');
    });
})();
</script>
