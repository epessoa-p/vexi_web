<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $client ? 'Editar cliente' : 'Nuevo cliente' }}</h1>
            <p class="text-muted mb-0">Datos del propietario y cómo contactarlo.</p>
        </div>
        <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Datos personales</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $client?->first_name) }}" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Apellidos</label>
                        <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $client?->last_name) }}" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de documento</label>
                        <select name="document_type" class="form-select">
                            <option value="">—</option>
                            @foreach(\App\Models\Client::DOCUMENT_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type', $client?->document_type ?? 'CI') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Número de documento</label>
                        <input type="text" name="document_number" class="form-control @error('document_number') is-invalid @enderror" value="{{ old('document_number', $client?->document_number) }}" maxlength="30">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-telephone"></i> Contacto</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" id="client-phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $client?->phone) }}" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-flex justify-content-between">
                            <span><i class="bi bi-whatsapp text-success"></i> WhatsApp</span>
                            <a href="#" class="small text-decoration-none" id="copy-phone">Igual que el teléfono</a>
                        </label>
                        <input type="text" name="whatsapp" id="client-whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ old('whatsapp', $client?->whatsapp) }}" maxlength="30" placeholder="+591 7xxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $client?->email) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $client?->address) }}" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $client?->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="client-active" {{ old('active', $client?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="client-active">Cliente activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button>
            @if(! $client && auth()->user()->hasPermissionInCompany('pets.create', $tenantCompany))
                <button class="btn btn-success" type="submit" name="after" value="pet"><i class="bi bi-plus-circle"></i> Guardar y registrar mascota</button>
            @endif
            <a href="{{ $client ? route('clients.show', $client) : route('clients.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

<script>
    document.getElementById('copy-phone')?.addEventListener('click', function (event) {
        event.preventDefault();
        document.getElementById('client-whatsapp').value = document.getElementById('client-phone').value;
    });
</script>
