@php
    $pet = $h->pet;
    $money = fn ($v) => \App\Support\Money::format($v, $company);
    $num = fn ($v) => \App\Support\Stock::number($v);
    $logo = $company?->logo ? asset('storage/' . $company->logo) : null;
    $evolution = $h->records->where('type', 'evolution')->sortBy('recorded_at');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen {{ $h->code }} · {{ $pet->name }}</title>
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
        .box { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; }
        .right { text-align: right; }
        .notes { white-space: pre-line; }
        .signature { margin-top: 36px; text-align: right; }
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
                <h1>{{ $h->status === 'deceased' ? 'Resumen de hospitalización' : 'Alta médica' }}</h1>
                <div class="small muted">{{ $h->code }} · {{ $h->discharged_at?->format('d/m/Y H:i') }}</div>
            </div>
        </header>

        <div class="box">
            <div><span class="muted">Paciente:</span> <strong>{{ $pet->name }}</strong></div>
            <div><span class="muted">Propietario:</span> {{ $pet->client?->full_name }}</div>
            <div><span class="muted">Especie / raza:</span> {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }}</div>
            <div><span class="muted">Sexo / edad:</span> {{ $pet->sex_label }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}</div>
            <div><span class="muted">Ingreso:</span> {{ $h->admitted_at->format('d/m/Y H:i') }}</div>
            <div><span class="muted">{{ $h->status === 'deceased' ? 'Fallecimiento' : 'Alta' }}:</span> {{ $h->discharged_at?->format('d/m/Y H:i') }} ({{ $h->stayDays() }} {{ $h->stayDays() === 1 ? 'día' : 'días' }})</div>
            <div><span class="muted">Motivo:</span> {{ $h->reason }}</div>
            <div><span class="muted">Diagnóstico de ingreso:</span> {{ $h->admission_diagnosis ?: '—' }}</div>
        </div>

        <h2>Diagnóstico final</h2>
        <div>{{ $h->final_diagnosis }}</div>

        @if($h->summary)
            <h2>Resumen</h2>
            <div class="notes">{{ $h->summary }}</div>
        @endif

        @if($h->treatments->isNotEmpty())
            <h2>Tratamientos recibidos</h2>
            <table>
                <thead><tr><th>Medicamento</th><th>Dosis</th><th>Frecuencia</th><th class="right">Tomas aplicadas</th></tr></thead>
                <tbody>
                    @foreach($h->treatments as $treatment)
                        <tr>
                            <td>{{ $treatment->medication }}</td>
                            <td>{{ $treatment->dose }} · {{ $treatment->route_label }}</td>
                            <td>cada {{ $treatment->frequency_hours }} h</td>
                            <td class="right">{{ $treatment->doses->where('status', 'given')->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if($evolution->isNotEmpty())
            <h2>Evolución</h2>
            @foreach($evolution as $record)
                <div style="margin-bottom: 4px"><strong>{{ $record->recorded_at->format('d/m H:i') }}</strong> <span class="muted">{{ $record->recorder?->name }}</span> — {{ $record->notes }}</div>
            @endforeach
        @endif

        @if($h->status !== 'deceased' && $h->home_treatment)
            <h2>Tratamiento domiciliario</h2>
            <div class="notes">{{ $h->home_treatment }}</div>
        @endif
        @if($h->discharge_instructions)
            <h2>Indicaciones</h2>
            <div class="notes">{{ $h->discharge_instructions }}</div>
        @endif

        <h2>Costos asociados</h2>
        <table>
            <thead><tr><th>Concepto</th><th class="right">Cant.</th><th class="right">Precio</th><th class="right">Total</th></tr></thead>
            <tbody>
                <tr><td>Estadía</td><td class="right">{{ $h->stayDays() }}</td><td class="right">{{ $money($h->daily_rate) }}</td><td class="right">{{ $money($h->stayCost()) }}</td></tr>
                @foreach($h->charges as $charge)
                    <tr><td>{{ $charge->description }}</td><td class="right">{{ $num($charge->quantity) }}</td><td class="right">{{ $money($charge->unit_price) }}</td><td class="right">{{ $money($charge->total) }}</td></tr>
                @endforeach
                <tr><td colspan="3" class="right"><strong>Total</strong></td><td class="right"><strong>{{ $money($h->totalCost()) }}</strong></td></tr>
            </tbody>
        </table>

        <div class="signature">
            <div>
                @if($h->veterinarian?->signature_url)<img src="{{ $h->veterinarian->signature_url }}" alt="Firma">@endif
                <div class="line">
                    <strong>{{ $h->veterinarian?->name }}</strong>
                    @if($h->veterinarian?->specialty)<br><span class="small">{{ $h->veterinarian->specialty }}</span>@endif
                    @if($h->veterinarian?->professional_license)<br><span class="small">Mat. prof. {{ $h->veterinarian->professional_license }}</span>@endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
