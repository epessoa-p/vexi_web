@extends('layouts.app')

@section('title', $species->name)

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $species->name }}</h1>
            <p class="text-muted mb-0">{{ $species->breeds->count() }} razas · {{ $species->pets_count }} mascotas registradas</p>
        </div>
        <div class="d-flex gap-2">
            @if($can('species.delete') && $species->pets_count === 0)
                <form action="{{ route('species.destroy', $species) }}" method="POST" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit" onclick="return confirm('¿Eliminar la especie {{ $species->name }} y sus razas?')"><i class="bi bi-trash"></i></button>
                </form>
            @endif
            <a href="{{ route('species.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @if($can('species.edit'))
        <form action="{{ route('species.update', $species) }}" method="POST" class="card border-0 shadow-sm mb-4">
            @csrf
            @method('PUT')
            <div class="card-body row g-2 align-items-center">
                <div class="col-md-6"><input type="text" name="name" class="form-control" value="{{ old('name', $species->name) }}" maxlength="80" required></div>
                <div class="col-md-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="species-active" @checked($species->active)>
                        <label class="form-check-label" for="species-active">Activa</label>
                    </div>
                </div>
                <div class="col-md-3 text-end"><button class="btn btn-primary" type="submit">Guardar especie</button></div>
            </div>
        </form>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold mb-3">Razas</h6>

            @if($can('species.create'))
                <form action="{{ route('species.breeds.store', $species) }}" method="POST" class="d-flex gap-2 mb-3">
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="Nueva raza" maxlength="120" required>
                    <button class="btn btn-success text-nowrap" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button>
                </form>
            @endif

            <div class="list-group">
                @forelse($species->breeds as $breed)
                    <div class="list-group-item">
                        @if($can('species.edit'))
                            <form action="{{ route('species.breeds.update', [$species, $breed]) }}" method="POST" class="d-flex gap-2 align-items-center">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $breed->name }}" maxlength="120" required>
                                <div class="form-check form-switch m-0" title="Activa">
                                    <input class="form-check-input" type="checkbox" name="active" value="1" @checked($breed->active)>
                                </div>
                                <span class="badge bg-light text-muted border text-nowrap">{{ $breedUsage[$breed->id] ?? 0 }} mascotas</span>
                                <button class="btn btn-sm btn-outline-primary" type="submit" title="Guardar"><i class="bi bi-check-lg"></i></button>
                                @if($can('species.delete') && ! ($breedUsage[$breed->id] ?? 0))
                                    <button class="btn btn-sm btn-outline-danger" type="submit" form="delete-breed-{{ $breed->id }}" title="Eliminar"
                                            onclick="return confirm('¿Eliminar la raza {{ $breed->name }}?')"><i class="bi bi-trash"></i></button>
                                @endif
                            </form>
                            <form id="delete-breed-{{ $breed->id }}" action="{{ route('species.breeds.destroy', [$species, $breed]) }}" method="POST" class="d-none">
                                @csrf
                                @method('DELETE')
                            </form>
                        @else
                            <div class="d-flex justify-content-between">
                                <span class="{{ $breed->active ? '' : 'text-muted text-decoration-line-through' }}">{{ $breed->name }}</span>
                                <span class="badge bg-light text-muted border">{{ $breedUsage[$breed->id] ?? 0 }} mascotas</span>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-muted text-center py-3">Esta especie no tiene razas registradas.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
