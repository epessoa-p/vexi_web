@extends('layouts.app')

@section('title', 'Categorías de gastos')

@section('page')
@php $canEdit = auth()->user()->hasPermissionInCompany('expenses.edit', $tenantCompany); @endphp
<div class="container-fluid" style="max-width: 800px;">
    <div class="mb-4">
        <h1 class="mb-1"><i class="bi bi-tags"></i> Categorías de gastos</h1>
        <p class="text-muted mb-0">Agrupan los gastos para los reportes.</p>
    </div>

    @if($canEdit)
        <form action="{{ route('expense-categories.store') }}" method="POST" class="card border-0 shadow-sm mb-3">
            @csrf
            <div class="card-body d-flex gap-2">
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="Nueva categoría" maxlength="80" required>
                <button class="btn btn-primary text-nowrap" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button>
            </div>
        </form>
    @endif

    <div class="list-group shadow-sm">
        @foreach($categories as $category)
            <div class="list-group-item">
                @if($canEdit)
                    <form action="{{ route('expense-categories.update', $category) }}" method="POST" class="d-flex gap-2 align-items-center">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $category->name }}" maxlength="80" required>
                        <div class="form-check form-switch m-0" title="Activa"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($category->active)></div>
                        <span class="badge bg-light text-muted border text-nowrap">{{ $category->expenses_count }} gastos</span>
                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-check-lg"></i></button>
                    </form>
                @else
                    <div class="d-flex justify-content-between"><span>{{ $category->name }}</span><span class="badge bg-light text-muted border">{{ $category->expenses_count }} gastos</span></div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
