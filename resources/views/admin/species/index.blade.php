@extends('layouts.app')

@section('title', 'Especies y razas')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-tags"></i> Especies y razas</h1>
            <p class="text-muted mb-0">Catálogo que se usa al registrar mascotas. Adáptalo a los pacientes que atiendes.</p>
        </div>
        @if($can('species.create'))
            <form action="{{ route('species.restore-defaults') }}" method="POST" class="m-0">
                @csrf
                <button class="btn btn-outline-secondary" type="submit" title="Agrega las especies y razas base que falten, sin tocar las que editaste"
                        onclick="return confirm('Se agregarán las especies y razas base que falten. Lo que ya editaste no cambia.')">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar catálogo base
                </button>
            </form>
        @endif
    </div>

    @if($can('species.create'))
        <form action="{{ route('species.store') }}" method="POST" class="card border-0 shadow-sm mb-3">
            @csrf
            <div class="card-body d-flex gap-2">
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Nueva especie (ej. Hurón)" maxlength="80" required>
                <button class="btn btn-primary text-nowrap" type="submit"><i class="bi bi-plus-lg"></i> Agregar especie</button>
            </div>
        </form>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Especie</th>
                            <th class="text-center">Razas</th>
                            <th class="text-center">Mascotas</th>
                            <th>Estado</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($species as $item)
                            <tr>
                                <td class="ps-3"><a href="{{ route('species.show', $item) }}" class="fw-semibold text-decoration-none">{{ $item->name }}</a></td>
                                <td class="text-center">{{ $item->breeds_count }}</td>
                                <td class="text-center">{{ $item->pets_count }}</td>
                                <td><span class="badge {{ $item->active ? 'bg-success' : 'bg-secondary' }}">{{ $item->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('species.show', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ul"></i> Razas</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">No hay especies. Usa «Restaurar catálogo base» para empezar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
