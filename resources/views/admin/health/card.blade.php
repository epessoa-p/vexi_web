@php
    $logo = $company?->logo ? asset('storage/' . $company->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnet sanitario · {{ $pet->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; background: #f3f4f6; }
        .sheet { background: #fff; max-width: 210mm; margin: 16px auto; padding: 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 12px; gap: 12px; }
        header img { max-height: 48px; max-width: 120px; object-fit: contain; }
        .company { font-weight: 700; font-size: 15px; }
        .muted { color: #6b7280; }
        .small { font-size: 10.5px; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 13px; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: .06em; }
        .patient { display: flex; gap: 14px; align-items: center; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
        .patient img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px 16px; flex: 1; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; }
        .empty { color: #9ca3af; text-align: center; }
        .toolbar { text-align: center; margin: 16px; }
        .toolbar button { font: inherit; padding: 8px 18px; border: 0; border-radius: 6px; background: #1f2937; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { box-shadow: none; margin: 0; padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Imprimir / guardar como PDF</button></div>

    <div class="sheet">
        <header>
            <div>
                @if($logo)<img src="{{ $logo }}" alt="{{ $company->name }}">@endif
                <div class="company">{{ $company?->name }}</div>
                <div class="small muted">{{ $company?->address }}@if($company?->phone) · Tel. {{ $company->phone }}@endif</div>
            </div>
            <div style="text-align: right">
                <h1>Carnet sanitario</h1>
                <div class="small muted">Emitido el {{ now()->format('d/m/Y') }}</div>
            </div>
        </header>

        <div class="patient">
            @if($pet->photo_url)<img src="{{ $pet->photo_url }}" alt="{{ $pet->name }}">@endif
            <div class="grid">
                <div><span class="muted">Nombre:</span> <strong>{{ $pet->name }}</strong></div>
                <div><span class="muted">Especie:</span> {{ $pet->species?->name }}</div>
                <div><span class="muted">Raza:</span> {{ $pet->breed?->name ?? '—' }}</div>
                <div><span class="muted">Sexo:</span> {{ $pet->sex_label }}</div>
                <div><span class="muted">Nacimiento:</span> {{ $pet->birth_date?->format('d/m/Y') ?? '—' }}</div>
                <div><span class="muted">Color:</span> {{ $pet->color ?: '—' }}</div>
                <div><span class="muted">Microchip:</span> {{ $pet->microchip ?: '—' }}</div>
                <div style="grid-column: span 2"><span class="muted">Propietario:</span> {{ $pet->client?->full_name }}{{ $pet->client?->phone ? ' · ' . $pet->client->phone : '' }}</div>
            </div>
        </div>

        <h2>Vacunas</h2>
        <table>
            <thead><tr><th>Fecha</th><th>Vacuna</th><th>Dosis</th><th>Lote / venc.</th><th>Veterinario</th><th>Próxima</th></tr></thead>
            <tbody>
                @forelse($pet->vaccinations->sortBy('applied_at') as $vaccination)
                    <tr>
                        <td>{{ $vaccination->applied_at->format('d/m/Y') }}</td>
                        <td>{{ $vaccination->vaccine?->name }}</td>
                        <td>{{ $vaccination->dose_number }}</td>
                        <td>{{ $vaccination->lot ?: '—' }}{{ $vaccination->lot_expires_at ? ' · ' . $vaccination->lot_expires_at->format('m/Y') : '' }}</td>
                        <td>{{ $vaccination->veterinarian?->name }}@if($vaccination->veterinarian?->professional_license)<div class="small muted">Mat. {{ $vaccination->veterinarian->professional_license }}</div>@endif</td>
                        <td>{{ $vaccination->next_due_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Sin vacunas registradas</td></tr>
                @endforelse
            </tbody>
        </table>

        <h2>Desparasitaciones</h2>
        <table>
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Dosis / peso</th><th>Responsable</th><th>Próxima</th></tr></thead>
            <tbody>
                @forelse($pet->dewormings->sortBy('applied_at') as $deworming)
                    <tr>
                        <td>{{ $deworming->applied_at->format('d/m/Y') }}</td>
                        <td>{{ $deworming->type_label }}</td>
                        <td>{{ $deworming->product }}</td>
                        <td>{{ $deworming->dose ?: '—' }}{{ $deworming->weight ? ' · ' . rtrim(rtrim(number_format($deworming->weight, 2), '0'), '.') . ' kg' : '' }}</td>
                        <td>{{ $deworming->veterinarian?->name }}</td>
                        <td>{{ $deworming->next_due_at?->format('d/m/Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Sin desparasitaciones registradas</td></tr>
                @endforelse
            </tbody>
        </table>

        <p class="small muted" style="margin-top: 18px">Documento emitido con Vexi a partir de los registros de la veterinaria.</p>
    </div>
</body>
</html>
