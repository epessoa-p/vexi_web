@php
    $l = $labRequest;
    $pet = $l->pet;
    $logo = $company?->logo ? asset('storage/' . $company->logo) : null;
    $flags = ['low' => 'Bajo', 'high' => 'Alto'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe {{ $l->code }} · {{ $pet->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; background: #f3f4f6; }
        .sheet { background: #fff; max-width: 210mm; margin: 16px auto; padding: 14mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        header { display: flex; justify-content: space-between; gap: 12px; border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 12px; }
        header img { max-height: 48px; max-width: 120px; object-fit: contain; }
        .company { font-weight: 700; font-size: 15px; }
        .muted { color: #6b7280; }
        .small { font-size: 10.5px; }
        h1 { font-size: 17px; margin: 0; }
        h2 { font-size: 13px; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: .06em; }
        .patient { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; }
        .value { text-align: right; font-weight: 600; }
        .low, .high { color: #b91c1c; font-weight: 700; }
        .notes { white-space: pre-line; }
        .signature { margin-top: 40px; text-align: right; }
        .signature div { display: inline-block; min-width: 60mm; text-align: center; }
        .signature img { max-height: 50px; max-width: 160px; display: block; margin: 0 auto 2px; }
        .signature .line { border-top: 1px solid #1f2937; padding-top: 4px; }
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
                <h1>{{ $l->studyType?->name }}</h1>
                <div class="small muted">Informe {{ $l->code }} · {{ $l->result_date?->format('d/m/Y') }}</div>
            </div>
        </header>

        <div class="patient">
            <div><span class="muted">Paciente:</span> <strong>{{ $pet->name }}</strong></div>
            <div><span class="muted">Propietario:</span> {{ $pet->client?->full_name }}</div>
            <div><span class="muted">Especie / raza:</span> {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }}</div>
            <div><span class="muted">Sexo / edad:</span> {{ $pet->sex_label }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}</div>
            <div><span class="muted">Solicitó:</span> {{ $l->requester?->name ?? '—' }}</div>
            <div><span class="muted">Fecha de solicitud:</span> {{ $l->requested_at->format('d/m/Y') }}</div>
        </div>

        @if($l->results->isNotEmpty())
            <h2>Resultados</h2>
            <table>
                <thead><tr><th>Parámetro</th><th style="text-align: right">Valor</th><th>Unidad</th><th>Referencia</th><th></th></tr></thead>
                <tbody>
                    @foreach($l->results as $result)
                        <tr>
                            <td>{{ $result->name }}</td>
                            <td class="value {{ $result->flag }}">{{ $result->value ?: '—' }}</td>
                            <td class="muted">{{ $result->unit }}</td>
                            <td class="muted">{{ $result->reference_label ?: '—' }}</td>
                            <td class="{{ $result->flag }}">{{ $flags[$result->flag] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($l->result_notes)
            <h2>Observaciones</h2>
            <div class="notes">{{ $l->result_notes }}</div>
        @endif

        @if($l->attachments->isNotEmpty())
            <h2>Archivos adjuntos</h2>
            <div class="small muted">{{ $l->attachments->pluck('original_name')->implode(' · ') }}</div>
        @endif

        <div class="signature">
            <div>
                @if($l->responsible?->signature_url)<img src="{{ $l->responsible->signature_url }}" alt="Firma">@endif
                <div class="line">
                    <strong>{{ $l->responsible?->name }}</strong>
                    @if($l->responsible?->specialty)<br><span class="small">{{ $l->responsible->specialty }}</span>@endif
                    @if($l->responsible?->professional_license)<br><span class="small">Mat. prof. {{ $l->responsible->professional_license }}</span>@endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
