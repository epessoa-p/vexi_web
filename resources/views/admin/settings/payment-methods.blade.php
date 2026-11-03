@extends('layouts.app')

@section('title', 'Métodos de pago')

@section('page')
@php $canEdit = auth()->user()->hasPermissionInCompany('sales.edit', $tenantCompany); @endphp
<div class="container-fluid" style="max-width: 900px;">
    <div class="mb-4">
        <h1 class="mb-1"><i class="bi bi-credit-card"></i> Métodos de pago</h1>
        <p class="text-muted mb-0">Los de tipo <strong>Efectivo</strong> cuentan para el arqueo de caja; el resto se informa por separado.</p>
    </div>

    @if($canEdit)
        <form action="{{ route('payment-methods.store') }}" method="POST" class="card border-0 shadow-sm mb-3">
            @csrf
            <div class="card-body d-flex gap-2 flex-wrap align-items-center">
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" style="max-width: 16rem" placeholder="Nombre (ej. Tigo Money)" maxlength="60" required>
                <select name="type" class="form-select" style="max-width: 14rem">
                    @foreach(\App\Models\PaymentMethod::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'other')>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-check m-0">
                    <input class="form-check-input" type="checkbox" name="requires_reference" value="1" id="new-reference">
                    <label class="form-check-label small" for="new-reference">Exige referencia</label>
                </div>
                <button class="btn btn-primary ms-auto" type="submit"><i class="bi bi-plus-lg"></i> Agregar</button>
            </div>
        </form>
    @endif

    <div class="list-group shadow-sm">
        @foreach($methods as $method)
            <div class="list-group-item">
                @if($canEdit)
                    <form action="{{ route('payment-methods.update', $method) }}" method="POST" class="d-flex gap-2 align-items-center flex-wrap">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" class="form-control form-control-sm" style="max-width: 16rem" value="{{ $method->name }}" maxlength="60" required>
                        <select name="type" class="form-select form-select-sm" style="max-width: 14rem">
                            @foreach(\App\Models\PaymentMethod::TYPES as $value => $label)
                                <option value="{{ $value }}" @selected($method->type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-check m-0"><input class="form-check-input" type="checkbox" name="requires_reference" value="1" @checked($method->requires_reference)><label class="form-check-label small">Referencia</label></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="active" value="1" @checked($method->active)><label class="form-check-label small">Activo</label></div>
                        <button class="btn btn-sm btn-outline-primary ms-auto" type="submit"><i class="bi bi-check-lg"></i></button>
                    </form>
                @else
                    <div class="d-flex justify-content-between"><span>{{ $method->name }}</span><span class="small text-muted">{{ $method->type_label }}{{ $method->active ? '' : ' · inactivo' }}</span></div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
