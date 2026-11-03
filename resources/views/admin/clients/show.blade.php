@extends('layouts.app')

@section('title', $client->full_name)

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">
                {{ $client->full_name }}
                <span class="badge fs-6 align-middle {{ $client->active ? 'bg-success' : 'bg-secondary' }}">{{ $client->active ? 'Activo' : 'Inactivo' }}</span>
            </h1>
            <p class="text-muted mb-0">Cliente desde {{ $client->created_at->format('d/m/Y') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($can('appointments.create') && $tenantCompany?->planAllows('agenda') && $client->pets->isNotEmpty())
                <a href="{{ route('appointments.create', ['client_id' => $client->id]) }}" class="btn btn-success"><i class="bi bi-calendar-plus"></i> Agendar cita</a>
            @endif
            @if($can('clients.edit'))
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
            @endif
            @if($can('clients.delete') && $client->pets->isEmpty())
                <form action="{{ route('clients.destroy', $client) }}" method="POST" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('¿Eliminar este cliente?')"><i class="bi bi-trash"></i></button>
                </form>
            @endif
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Datos del cliente</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-normal">Documento</dt>
                        <dd class="col-7">{{ $client->document_number ? "{$client->document_type} {$client->document_number}" : '-' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Teléfono</dt>
                        <dd class="col-7">{{ $client->phone ?: '-' }}</dd>
                        <dt class="col-5 text-muted fw-normal">WhatsApp</dt>
                        <dd class="col-7">
                            @if($client->whatsapp_url)
                                <a href="{{ $client->whatsapp_url }}" target="_blank" rel="noopener" class="text-success text-decoration-none"><i class="bi bi-whatsapp"></i> {{ $client->whatsapp }}</a>
                            @else
                                -
                            @endif
                        </dd>
                        <dt class="col-5 text-muted fw-normal">Correo</dt>
                        <dd class="col-7 text-break">{{ $client->email ?: '-' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Dirección</dt>
                        <dd class="col-7">{{ $client->address ?: '-' }}</dd>
                    </dl>
                    @if($client->notes)
                        <hr>
                        <div class="small text-muted mb-1">Observaciones</div>
                        <p class="small mb-0" style="white-space: pre-line">{{ $client->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-receipt"></i> Cuenta del cliente</h6>
                    @if($sales === null)
                        <p class="small text-muted mb-0">El plan de la veterinaria no incluye ventas.</p>
                    @else
                        @php $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany); @endphp
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-muted">Saldo pendiente</span>
                            <span class="fw-bold {{ $balance > 0 ? 'text-danger' : 'text-success' }}">{{ $money($balance) }}</span>
                        </div>
                        @forelse($sales as $sale)
                            @php [$label, $color] = $sale->payment_status_badge; @endphp
                            <a href="{{ route('sales.show', $sale) }}" class="d-flex justify-content-between border-top py-1 small text-decoration-none text-reset">
                                <span>{{ $sale->code }} · {{ $sale->created_at->format('d/m/Y') }} <span class="badge bg-{{ $color }}">{{ $label }}</span></span>
                                <span>{{ $money($sale->total) }}</span>
                            </a>
                        @empty
                            <p class="small text-muted mb-0">Sin compras registradas.</p>
                        @endforelse
                        @if($can('sales.create'))
                            <a href="{{ route('pos.create', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-success w-100 mt-2"><i class="bi bi-cart-plus"></i> Nueva venta</a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-heart-pulse"></i> Mascotas ({{ $client->pets->count() }})</h6>
                        @if($can('pets.create'))
                            <a href="{{ route('pets.create', ['client_id' => $client->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Registrar mascota</a>
                        @endif
                    </div>

                    <div class="row g-3">
                        @forelse($client->pets as $pet)
                            <div class="col-md-6">
                                <a href="{{ route('pets.show', $pet) }}" class="card h-100 text-decoration-none text-reset border">
                                    <div class="card-body d-flex gap-3 align-items-center">
                                        @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 56])
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold">
                                                {{ $pet->name }}
                                                @if($pet->hasClinicalWarnings())
                                                    <i class="bi bi-exclamation-triangle-fill text-danger" title="Tiene alertas clínicas"></i>
                                                @endif
                                            </div>
                                            <div class="small text-muted">{{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }}</div>
                                            <div class="small text-muted">{{ $pet->sex_label }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}</div>
                                        </div>
                                        @if($pet->status !== 'active')
                                            <span class="badge bg-secondary">{{ $pet->status_label }}</span>
                                        @endif
                                    </div>
                                </a>
                            </div>
                        @empty
                            <div class="col-12 text-center text-muted py-4">Este cliente todavía no tiene mascotas registradas.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
