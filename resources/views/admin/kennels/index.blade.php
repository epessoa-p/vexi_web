@extends('layouts.app')

@section('title', 'Espacios de internación')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="mb-4">
        <h1 class="mb-1"><i class="bi bi-grid-3x3-gap"></i> Espacios de internación</h1>
        <p class="text-muted mb-0">Jaulas, caniles y demás lugares donde se aloja a los pacientes.</p>
    </div>

    @if($can('kennels.create'))
        <form action="{{ route('kennels.store') }}" method="POST" class="card border-0 shadow-sm mb-3">
            @csrf
            <div class="card-body d-flex gap-2 flex-wrap">
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" style="max-width: 18rem" placeholder="Nombre (ej. Canil 1)" maxlength="80" required>
                @if($branches->count() > 1)
                    <select name="branch_id" class="form-select" style="max-width: 14rem">
                        <option value="">Sucursal…</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                @elseif($branches->count() === 1)
                    <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
                @endif
                <input type="text" name="notes" class="form-control" style="max-width: 18rem" placeholder="Observaciones" maxlength="255">
                <button class="btn btn-primary ms-auto text-nowrap" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button>
            </div>
        </form>
    @endif

    <div class="list-group shadow-sm">
        @forelse($kennels as $kennel)
            <div class="list-group-item">
                @if($can('kennels.edit'))
                    <form action="{{ route('kennels.update', $kennel) }}" method="POST" class="d-flex gap-2 align-items-center flex-wrap">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" class="form-control form-control-sm" style="max-width: 16rem" value="{{ $kennel->name }}" maxlength="80" required>
                        @if($branches->count() > 1)
                            <select name="branch_id" class="form-select form-select-sm" style="max-width: 12rem">
                                <option value="">Sucursal…</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($kennel->branch_id === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="branch_id" value="{{ $kennel->branch_id }}">
                        @endif
                        <input type="text" name="notes" class="form-control form-control-sm" style="max-width: 14rem" value="{{ $kennel->notes }}" maxlength="255" placeholder="Observaciones">
                        <div class="form-check form-switch m-0" title="Activo"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($kennel->active)></div>
                        <span class="badge {{ $occupied->contains($kennel->id) ? 'bg-danger' : 'bg-success' }}">{{ $occupied->contains($kennel->id) ? 'Ocupado' : 'Libre' }}</span>
                        <button class="btn btn-sm btn-outline-primary ms-auto" type="submit" title="Guardar"><i class="bi bi-check-lg"></i></button>
                        @if($can('kennels.delete') && ! $kennel->hospitalizations_count)
                            <button class="btn btn-sm btn-outline-danger" type="submit" form="delete-kennel-{{ $kennel->id }}" title="Eliminar" onclick="return confirm('¿Eliminar este espacio?')"><i class="bi bi-trash"></i></button>
                        @endif
                    </form>
                    <form id="delete-kennel-{{ $kennel->id }}" action="{{ route('kennels.destroy', $kennel) }}" method="POST" class="d-none">@csrf @method('DELETE')</form>
                @else
                    <div class="d-flex justify-content-between">
                        <span>{{ $kennel->name }}{{ $kennel->branch ? ' · ' . $kennel->branch->name : '' }}</span>
                        <span class="badge {{ $occupied->contains($kennel->id) ? 'bg-danger' : 'bg-success' }}">{{ $occupied->contains($kennel->id) ? 'Ocupado' : 'Libre' }}</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="list-group-item text-muted text-center py-4">Todavía no hay espacios.</div>
        @endforelse
    </div>
</div>
@endsection
