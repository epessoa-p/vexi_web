@extends('layouts.app')

@section('title', 'Servicios')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-card-checklist"></i> Servicios</h1>
            <p class="text-muted mb-0">Lo que ofrece la veterinaria: precio, duración y color en la agenda.</p>
        </div>
        @if($can('services.create'))
            <a href="{{ route('services.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo servicio</a>
        @endif
    </div>

    <form method="GET" class="mb-3 d-flex gap-2 flex-wrap">
        <a href="{{ route('services.index') }}" class="btn btn-sm {{ request('category') ? 'btn-light border' : 'btn-dark' }}">Todos</a>
        @foreach(\App\Models\Service::CATEGORIES as $value => [$label, $color])
            <a href="{{ route('services.index', ['category' => $value]) }}" class="btn btn-sm {{ request('category') === $value ? 'btn-dark' : 'btn-light border' }}">
                <span class="d-inline-block rounded-circle me-1" style="width: .6rem; height: .6rem; background: {{ $color }}"></span>{{ $label }}
            </a>
        @endforeach
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Servicio</th>
                            <th>Categoría</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Duración</th>
                            <th class="text-center">Citas</th>
                            <th>Estado</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr>
                                <td class="ps-3">
                                    <span class="d-inline-block rounded me-2 align-middle" style="width: .8rem; height: .8rem; background: {{ $service->calendar_color }}"></span>
                                    <span class="fw-semibold">{{ $service->name }}</span>
                                    @if($service->description)<div class="small text-muted ms-4">{{ \Illuminate\Support\Str::limit($service->description, 80) }}</div>@endif
                                </td>
                                <td>{{ $service->category_label }}</td>
                                <td class="text-end">{{ \App\Support\Money::format($service->price, $tenantCompany) }}</td>
                                <td class="text-end">{{ $service->duration_minutes }} min</td>
                                <td class="text-center">{{ $service->appointments_count }}</td>
                                <td><span class="badge {{ $service->active ? 'bg-success' : 'bg-secondary' }}">{{ $service->active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($can('services.edit'))
                                        <a href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    @endif
                                    @if($can('services.delete'))
                                        <form action="{{ route('services.destroy', $service) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar el servicio {{ $service->name }}? Las citas pasadas lo conservan.')"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay servicios en esta categoría.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
