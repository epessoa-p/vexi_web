@php $s = $supplier; @endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0">{{ $s ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
        <a href="{{ $s ? route('suppliers.show', $s) : route('suppliers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST" class="card border-0 shadow-sm">
        @csrf
        @if($method !== 'POST') @method($method) @endif
        @if(request('return'))<input type="hidden" name="return" value="{{ request('return') }}">@endif
        <div class="card-body p-4 row g-3">
            <div class="col-md-8">
                <label class="form-label">Nombre o razón social <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $s?->name) }}" maxlength="160" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">NIT</label>
                <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $s?->tax_id) }}" maxlength="32">
            </div>
            <div class="col-md-4">
                <label class="form-label">Persona de contacto</label>
                <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $s?->contact_name) }}" maxlength="120">
            </div>
            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $s?->phone) }}" maxlength="30">
            </div>
            <div class="col-md-4">
                <label class="form-label">Correo</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $s?->email) }}">
            </div>
            <div class="col-12">
                <label class="form-label">Dirección</label>
                <input type="text" name="address" class="form-control" value="{{ old('address', $s?->address) }}" maxlength="255">
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="notes" rows="2" class="form-control" placeholder="Condiciones de pago, días de entrega…">{{ old('notes', $s?->notes) }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $s?->active ?? true))>
                    <label class="form-check-label" for="active">Activo</label>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
                <a href="{{ $s ? route('suppliers.show', $s) : route('suppliers.index') }}" class="btn btn-light border">Cancelar</a>
            </div>
        </div>
    </form>
</div>
