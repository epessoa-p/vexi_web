@extends('layouts.app')

@section('title', 'Categorías de productos')

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="mb-4">
        <h1 class="mb-1"><i class="bi bi-bookmarks"></i> Categorías de productos</h1>
        <p class="text-muted mb-0">Agrupan el catálogo de farmacia e inventario.</p>
    </div>

    @if($can('products.create'))
        <form action="{{ route('product-categories.store') }}" method="POST" class="card border-0 shadow-sm mb-3">
            @csrf
            <div class="card-body d-flex gap-2">
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Nueva categoría" maxlength="80" required>
                <button class="btn btn-primary text-nowrap" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button>
            </div>
        </form>
    @endif

    <div class="list-group shadow-sm">
        @forelse($categories as $category)
            <div class="list-group-item">
                @if($can('products.edit'))
                    <form action="{{ route('product-categories.update', $category) }}" method="POST" class="d-flex gap-2 align-items-center">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $category->name }}" maxlength="80" required>
                        <div class="form-check form-switch m-0" title="Activa">
                            <input class="form-check-input" type="checkbox" name="active" value="1" @checked($category->active)>
                        </div>
                        <a href="{{ route('products.index', ['category_id' => $category->id]) }}" class="badge bg-light text-muted border text-nowrap text-decoration-none">{{ $category->products_count }} productos</a>
                        <button class="btn btn-sm btn-outline-primary" type="submit" title="Guardar"><i class="bi bi-check-lg"></i></button>
                        @if($can('products.delete') && ! $category->products_count)
                            <button class="btn btn-sm btn-outline-danger" type="submit" form="delete-category-{{ $category->id }}" title="Eliminar"
                                    onclick="return confirm('¿Eliminar la categoría {{ $category->name }}?')"><i class="bi bi-trash"></i></button>
                        @endif
                    </form>
                    <form id="delete-category-{{ $category->id }}" action="{{ route('product-categories.destroy', $category) }}" method="POST" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                @else
                    <div class="d-flex justify-content-between">
                        <span>{{ $category->name }}</span>
                        <span class="badge bg-light text-muted border">{{ $category->products_count }} productos</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="list-group-item text-muted text-center py-4">No hay categorías.</div>
        @endforelse
    </div>
</div>
@endsection
