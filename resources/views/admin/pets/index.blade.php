@extends('layouts.app')

@section('title', 'Mascotas')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-heart-pulse"></i> Mascotas</h1>
            <p class="text-muted mb-0">Pacientes de la veterinaria. Cada uno con su propia ficha clínica.</p>
        </div>
        @if($can('pets.create'))
            <a href="{{ route('pets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva mascota</a>
        @endif
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre, microchip o datos del propietario" autofocus>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Especie</label>
                <select name="species_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($species as $item)
                        <option value="{{ $item->id }}" @selected((string) request('species_id') === (string) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Pet::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill" type="submit"><i class="bi bi-search"></i> Buscar</button>
                @if(request()->hasAny(['q', 'species_id', 'status']))
                    <a href="{{ route('pets.index') }}" class="btn btn-light border" title="Limpiar"><i class="bi bi-x-lg"></i></a>
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
                            <th class="ps-3">Mascota</th>
                            <th>Especie / raza</th>
                            <th>Sexo</th>
                            <th>Edad</th>
                            <th>Propietario</th>
                            <th>Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pets as $pet)
                            <tr>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 36])
                                        <div>
                                            <a href="{{ route('pets.show', $pet) }}" class="fw-semibold text-decoration-none">{{ $pet->name }}</a>
                                            @if($pet->hasClinicalWarnings())
                                                <i class="bi bi-exclamation-triangle-fill text-danger" title="Tiene alertas clínicas"></i>
                                            @endif
                                            @if($pet->microchip)<div><small class="text-muted">Chip {{ $pet->microchip }}</small></div>@endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $pet->species?->name }}<div><small class="text-muted">{{ $pet->breed?->name ?? 'Sin raza' }}</small></div></td>
                                <td>{{ $pet->sex_label }}</td>
                                <td>{{ $pet->age_label ?? '-' }}</td>
                                <td>
                                    @if($pet->client)
                                        <a href="{{ route('clients.show', $pet->client) }}" class="text-decoration-none">{{ $pet->client->full_name }}</a>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ match ($pet->status) { 'active' => 'bg-success', 'deceased' => 'bg-dark', default => 'bg-secondary' } }}">{{ $pet->status_label }}</span>
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    <a href="{{ route('pets.show', $pet) }}" class="btn btn-sm btn-outline-secondary" title="Ficha"><i class="bi bi-clipboard2-pulse"></i></a>
                                    @if($can('pets.edit'))
                                        <a href="{{ route('pets.edit', $pet) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">
                                {{ request()->hasAny(['q', 'species_id', 'status']) ? 'Ninguna mascota coincide con la búsqueda.' : 'No hay mascotas registradas.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $pets->links() }}</div>
</div>
@endsection
