@extends('layouts.app')

@section('title', 'Vacunas')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-capsule"></i> Catálogo de vacunas</h1>
            <p class="text-muted mb-0">Vacunas que aplica la veterinaria y su protocolo recomendado.</p>
        </div>
        @if($can('vaccines.create'))
            <a href="{{ route('vaccines.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva vacuna</a>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Vacuna</th>
                            <th>Especies</th>
                            <th>Protocolo</th>
                            <th class="text-center">Aplicaciones</th>
                            <th>Estado</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vaccines as $vaccine)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $vaccine->name }}</div>
                                    <small class="text-muted">{{ collect([$vaccine->manufacturer, $vaccine->presentation])->filter()->implode(' · ') }}</small>
                                </td>
                                <td>{{ $vaccine->species->pluck('name')->implode(', ') ?: 'Todas' }}</td>
                                <td class="small">{{ $vaccine->protocol_label }}</td>
                                <td class="text-center">{{ $vaccine->vaccinations_count }}</td>
                                <td><span class="badge {{ $vaccine->active ? 'bg-success' : 'bg-secondary' }}">{{ $vaccine->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($can('vaccines.edit'))
                                        <a href="{{ route('vaccines.edit', $vaccine) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    @endif
                                    @if($can('vaccines.delete'))
                                        <form action="{{ route('vaccines.destroy', $vaccine) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Quitar {{ $vaccine->name }} del catálogo? Las aplicaciones registradas se conservan.')"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay vacunas en el catálogo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
