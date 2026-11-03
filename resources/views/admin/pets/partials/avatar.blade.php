{{-- Foto de la mascota, o sus iniciales si no tiene. Parámetros: $pet, $size (px). --}}
@php $size = $size ?? 48; @endphp
@if($pet->photo_url)
    <img src="{{ $pet->photo_url }}" alt="{{ $pet->name }}" class="rounded-circle object-fit-cover flex-shrink-0 border"
         style="width: {{ $size }}px; height: {{ $size }}px;">
@else
    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 bg-primary-subtle text-primary fw-semibold border"
         style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size * 0.38) }}px;">
        {{ mb_strtoupper(mb_substr($pet->name, 0, 1)) }}
    </div>
@endif
