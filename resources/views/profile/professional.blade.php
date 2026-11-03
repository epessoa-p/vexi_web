@extends('layouts.app')

@section('title', 'Mi perfil profesional')

@section('page')
<div class="container-fluid" style="max-width: 760px;">
    <div class="mb-4">
        <h1 class="mb-1"><i class="bi bi-person-badge"></i> Mi perfil profesional</h1>
        <p class="text-muted mb-0">Estos datos aparecen al pie de las recetas que emites.</p>
    </div>

    <form action="{{ route('profile.professional.update') }}" method="POST" enctype="multipart/form-data" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body p-4 row g-3">
            <div class="col-12">
                <label class="form-label">Nombre</label>
                <input type="text" class="form-control" value="{{ $user->name }}" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Matrícula o registro profesional</label>
                <input type="text" name="professional_license" class="form-control @error('professional_license') is-invalid @enderror" value="{{ old('professional_license', $user->professional_license) }}" maxlength="60">
            </div>
            <div class="col-md-6">
                <label class="form-label">Especialidad</label>
                <input type="text" name="specialty" class="form-control @error('specialty') is-invalid @enderror" value="{{ old('specialty', $user->specialty) }}" maxlength="120" placeholder="Ej.: Médico veterinario zootecnista">
            </div>
            <div class="col-12">
                <label class="form-label">Firma</label>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="border rounded bg-white d-flex align-items-center justify-content-center" style="width: 220px; height: 90px;">
                        @if($user->signature_url)
                            <img src="{{ $user->signature_url }}" alt="Firma" style="max-width: 200px; max-height: 80px;">
                        @else
                            <span class="text-muted small">Sin firma</span>
                        @endif
                    </div>
                    <div>
                        <input type="file" name="signature" accept="image/*" class="form-control @error('signature') is-invalid @enderror">
                        <div class="form-text">Imagen PNG con fondo transparente o blanco, hasta 2 MB.</div>
                        @if($user->signature)
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="remove-signature">
                                <label class="form-check-label" for="remove-signature">Quitar la firma actual</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Guardar</button></div>
        </div>
    </form>
</div>
@endsection
