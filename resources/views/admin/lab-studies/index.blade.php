@extends('layouts.app')

@section('title', 'Estudios de laboratorio')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-clipboard2-data"></i> Estudios de laboratorio</h1>
            <p class="text-muted mb-0">Tipos de estudio, sus parámetros y valores de referencia.</p>
        </div>
        @if($can('lab_studies.create'))
            <a href="{{ route('lab-studies.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo estudio</a>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Estudio</th><th>Categoría</th><th class="text-center">Parámetros</th><th class="text-center">Solicitudes</th><th>Estado</th><th class="pe-3"></th></tr></thead>
                    <tbody>
                        @forelse($studyTypes as $study)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $study->name }}</td>
                                <td class="small">{{ $study->category_label }}</td>
                                <td class="text-center">{{ $study->parameters_count }}</td>
                                <td class="text-center">{{ $study->requests_count }}</td>
                                <td><span class="badge {{ $study->active ? 'bg-success' : 'bg-secondary' }}">{{ $study->active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="pe-3 text-end text-nowrap">
                                    @if($can('lab_studies.edit'))
                                        <a href="{{ route('lab-studies.edit', $study) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    @endif
                                    @if($can('lab_studies.delete') && ! $study->requests_count)
                                        <form action="{{ route('lab-studies.destroy', $study) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este estudio del catálogo?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No hay estudios en el catálogo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
