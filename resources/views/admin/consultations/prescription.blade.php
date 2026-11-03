@php
    $c = $consultation;
    $pet = $c->pet;
    $vet = $c->veterinarian;
    $logo = $company?->logo ? asset('storage/' . $company->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta {{ $c->prescription_number }} · {{ $pet->name }}</title>
    <style>
        @page { size: A5 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; color: #1f2937; font-size: 12px; margin: 0; background: #f3f4f6; }
        .sheet { background: #fff; max-width: 148mm; min-height: 200mm; margin: 16px auto; padding: 12mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); display: flex; flex-direction: column; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 10px; gap: 12px; }
        header img { max-height: 48px; max-width: 120px; object-fit: contain; }
        .company { font-weight: 700; font-size: 15px; }
        .muted { color: #6b7280; }
        .small { font-size: 10.5px; }
        .meta { text-align: right; }
        .patient { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; margin-bottom: 12px; }
        h2 { font-size: 13px; margin: 14px 0 6px; text-transform: uppercase; letter-spacing: .06em; }
        .rx { font-size: 26px; font-weight: 700; font-family: Georgia, serif; margin: 6px 0 2px; }
        ol { padding-left: 18px; margin: 0; }
        li { margin-bottom: 8px; }
        .drug { font-weight: 700; font-size: 13px; }
        .instructions { white-space: pre-line; }
        footer { margin-top: auto; padding-top: 24px; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; }
        .signature { text-align: center; min-width: 55mm; }
        .signature img { max-height: 50px; max-width: 160px; display: block; margin: 0 auto 2px; }
        .signature .line { border-top: 1px solid #1f2937; padding-top: 4px; }
        .toolbar { text-align: center; margin: 16px; }
        .toolbar button { font: inherit; padding: 8px 18px; border: 0; border-radius: 6px; background: #1f2937; color: #fff; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; min-height: calc(210mm - 24mm); }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir / guardar como PDF</button>
    </div>

    <div class="sheet">
        <header>
            <div>
                @if($logo)<img src="{{ $logo }}" alt="{{ $company->name }}">@endif
                <div class="company">{{ $company?->name }}</div>
                <div class="small muted">
                    {{ $company?->address }}
                    @if($company?->phone) · Tel. {{ $company->phone }} @endif
                    @if($company?->tax_id) · {{ $company->tax_id_label }} {{ $company->tax_id }} @endif
                </div>
            </div>
            <div class="meta">
                <div><strong>Receta {{ $c->prescription_number }}</strong></div>
                <div class="small muted">{{ $c->started_at->format('d/m/Y') }}</div>
            </div>
        </header>

        <div class="patient">
            <div><span class="muted">Paciente:</span> <strong>{{ $pet->name }}</strong></div>
            <div><span class="muted">Propietario:</span> {{ $c->client?->full_name }}</div>
            <div><span class="muted">Especie / raza:</span> {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }}</div>
            <div><span class="muted">Sexo / edad:</span> {{ $pet->sex_label }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}</div>
            @if($c->weight !== null)<div><span class="muted">Peso:</span> {{ rtrim(rtrim(number_format($c->weight, 2), '0'), '.') }} kg</div>@endif
            @if($c->primary_diagnosis)<div><span class="muted">Diagnóstico:</span> {{ $c->primary_diagnosis->description }}</div>@endif
        </div>

        @if($c->prescriptionItems->isNotEmpty())
            <div class="rx">℞</div>
            <ol>
                @foreach($c->prescriptionItems as $item)
                    <li>
                        <div class="drug">{{ $item->medication }}{{ $item->presentation ? " — {$item->presentation}" : '' }}{{ $item->quantity ? " (cant. {$item->quantity})" : '' }}</div>
                        <div>
                            Administrar {{ $item->dose }} vía {{ mb_strtolower($item->route_label) }}, {{ $item->frequency }}{{ $item->duration ? ", durante {$item->duration}" : '' }}.
                            @if($item->instructions) {{ $item->instructions }} @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        @if($c->instructions)
            <h2>Indicaciones</h2>
            <div class="instructions">{{ $c->instructions }}</div>
        @endif

        @if($c->next_review_at)
            <h2>Próxima revisión</h2>
            <div>{{ ucfirst($c->next_review_at->translatedFormat('l j \d\e F \d\e Y')) }}</div>
        @endif

        <footer>
            <div class="small muted">Documento emitido con Vexi.</div>
            <div class="signature">
                @if($vet?->signature_url)<img src="{{ $vet->signature_url }}" alt="Firma">@endif
                <div class="line">
                    <strong>{{ $vet?->name }}</strong>
                    @if($vet?->specialty)<div class="small">{{ $vet->specialty }}</div>@endif
                    @if($vet?->professional_license)<div class="small">Mat. prof. {{ $vet->professional_license }}</div>@endif
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
