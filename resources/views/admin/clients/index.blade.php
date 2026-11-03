@extends('layouts.app')

@section('title', 'Clientes')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-people"></i> Clientes</h1>
            <p class="text-muted mb-0">Propietarios de las mascotas atendidas en la veterinaria.</p>
        </div>
        @if($can('clients.create'))
            <a href="{{ route('clients.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo cliente</a>
        @endif
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre, documento, teléfono o correo" autofocus>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="active" @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-search"></i> Buscar</button>
                @if(request()->hasAny(['q', 'status']))
                    <a href="{{ route('clients.index') }}" class="btn btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Cliente</th>
                            <th>Documento</th>
                            <th>Contacto</th>
                            <th class="text-center">Mascotas</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            <tr>
                                <td class="ps-3">
                                    <a href="{{ route('clients.show', $client) }}" class="fw-semibold text-decoration-none">{{ $client->full_name }}</a>
                                    @if($client->email)<div><small class="text-muted">{{ $client->email }}</small></div>@endif
                                </td>
                                <td>
                                    @if($client->document_number)
                                        <small class="text-muted">{{ $client->document_type }}</small> {{ $client->document_number }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    {{ $client->phone ?: '-' }}
                                    @if($client->whatsapp_url)
                                        <a href="{{ $client->whatsapp_url }}" target="_blank" rel="noopener" class="text-success ms-1" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge bg-light text-dark border">{{ $client->pets_count }}</span></td>
                                <td><span class="badge {{ $client->active ? 'bg-success' : 'bg-secondary' }}">{{ $client->active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                    @if($can('pets.create'))
                                        <a href="{{ route('pets.create', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-success" title="Registrar mascota"><i class="bi bi-plus-circle"></i></a>
                                    @endif
                                    @if($can('clients.edit'))
                                        <a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">
                                {{ request()->hasAny(['q', 'status']) ? 'Ningún cliente coincide con la búsqueda.' : 'No hay clientes registrados.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $clients->links() }}</div>
</div>
@endsection
