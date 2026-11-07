@extends('layouts.app')

@section('title', "Internación de {$h->pet?->name}")

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $money = fn ($v) => \App\Support\Money::format($v, $tenantCompany);
    $num = fn ($v) => \App\Support\Stock::number($v);
    $pet = $h->pet;
    $canEdit = $h->isActive() && $can('hospitalizations.edit');
    $canClose = $h->isActive() && $can('hospitalizations.create');
    $tab = in_array(request('tab'), ['treatments', 'records', 'costs'], true) ? request('tab') : 'treatments';
    if ($errors->hasAny(['type', 'recorded_at', 'notes', 'temperature', 'heart_rate', 'respiratory_rate', 'weight'])) { $tab = 'records'; }
    if ($errors->hasAny(['description', 'quantity', 'unit_price'])) { $tab = 'costs'; }
    $doses = $h->treatments->flatMap(fn ($t) => $t->doses->each(fn ($d) => $d->setRelation('treatment', $t)))->sortBy('scheduled_at')->values();
    $chargesTotal = (float) $h->charges->sum('total');
    $nowInput = now()->format('Y-m-d\TH:i');
@endphp
<div class="container-fluid" style="max-width: 1300px;">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 60])
            <div>
                <h1 class="mb-0 h3">
                    {{ $pet->name }}
                    <span class="badge bg-{{ $h->status_color }} fs-6 align-middle">{{ $h->status_label }}</span>
                    @if($h->isActive())<span class="badge bg-{{ $h->condition_color }} fs-6 align-middle">{{ $h->condition_label }}</span>@endif
                </h1>
                <div class="text-muted small">
                    {{ $h->code }} · {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }}{{ $pet->age_label ? ' · ' . $pet->age_label : '' }}
                    · <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'record']) }}" class="text-decoration-none">Ficha</a>
                    · {{ $pet->client?->full_name }}{{ $pet->client?->phone ? ' · ' . $pet->client->phone : '' }}
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(in_array($h->status, ['discharged', 'deceased'], true))
                <a href="{{ route('hospitalizations.report', $h) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Resumen</a>
            @endif
            <a href="{{ route('hospitalizations.index') }}" class="btn btn-outline-secondary">Hospitalización</a>
        </div>
    </div>

    @if($pet->hasClinicalWarnings())
        <div class="alert alert-danger d-flex gap-2 align-items-start py-2">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                @if($pet->clinical_alerts)<strong>Alerta:</strong> {{ $pet->clinical_alerts }}@endif
                @if($pet->allergies) <strong>Alergias:</strong> {{ $pet->allergies }}@endif
            </div>
        </div>
    @endif
    @if($h->status === 'cancelled')
        <div class="alert alert-secondary"><i class="bi bi-x-octagon"></i> Internación anulada: {{ $h->cancel_reason }}</div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-3">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item"><button class="nav-link {{ $tab === 'treatments' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-treatments" type="button"><i class="bi bi-capsule"></i> Tratamientos</button></li>
                        <li class="nav-item"><button class="nav-link {{ $tab === 'records' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-records" type="button"><i class="bi bi-journal-medical"></i> Cuidados y evolución</button></li>
                        <li class="nav-item"><button class="nav-link {{ $tab === 'costs' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-costs" type="button"><i class="bi bi-cash-stack"></i> Costos</button></li>
                    </ul>
                </div>
                <div class="card-body tab-content">
                    {{-- Tratamientos programados --}}
                    <div class="tab-pane fade {{ $tab === 'treatments' ? 'show active' : '' }}" id="tab-treatments">
                        @foreach($h->treatments as $treatment)
                            <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                                <div class="small">
                                    <strong>{{ $treatment->medication }}</strong> · {{ $treatment->dose }} · {{ $treatment->route_label }} · cada {{ $treatment->frequency_hours }} h
                                    <div class="text-muted">
                                        {{ $treatment->starts_at->format('d/m H:i') }} a {{ $treatment->ends_at->format('d/m H:i') }}
                                        @if($treatment->product && (float) $treatment->stock_quantity > 0) · descuenta {{ $num($treatment->stock_quantity) }} del inventario por toma @endif
                                        @if($treatment->instructions) · {{ $treatment->instructions }} @endif
                                    </div>
                                    @if($treatment->stopped_at)<span class="badge bg-dark">Suspendido {{ $treatment->stopped_at->format('d/m H:i') }}</span>@endif
                                </div>
                                @if($canEdit && ! $treatment->stopped_at && $treatment->doses->contains('status', 'pending'))
                                    <form action="{{ route('hospitalizations.treatments.stop', [$h, $treatment]) }}" method="POST" class="m-0" onsubmit="return confirm('¿Suspender este tratamiento? Se cancelan sus tomas pendientes.')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger text-nowrap" type="submit">Suspender</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach

                        @if($canEdit)
                            <details class="border rounded p-2 mb-3" {{ $errors->hasAny(['medication', 'dose', 'route', 'frequency_hours', 'starts_at', 'ends_at', 'stock_quantity']) || $h->treatments->isEmpty() ? 'open' : '' }}>
                                <summary class="fw-semibold small"><i class="bi bi-plus-lg"></i> Programar medicamento</summary>
                                <form action="{{ route('hospitalizations.treatments.store', $h) }}" method="POST" class="row g-2 mt-1">
                                    @csrf
                                    @if($products->isNotEmpty())
                                        <div class="col-md-8">
                                            <label class="form-label small mb-1">Producto del inventario</label>
                                            <select name="product_id" class="form-select form-select-sm">
                                                <option value="">No descontar del inventario</option>
                                                @foreach($products as $product)
                                                    <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1">Unidades por toma</label>
                                            <input type="number" name="stock_quantity" step="0.01" min="0" class="form-control form-control-sm @error('stock_quantity') is-invalid @enderror" value="{{ old('stock_quantity') }}" placeholder="Ej.: 0.5">
                                        </div>
                                    @endif
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">Medicamento</label>
                                        <input type="text" name="medication" class="form-control form-control-sm @error('medication') is-invalid @enderror" value="{{ old('medication') }}" maxlength="255">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Dosis <span class="text-danger">*</span></label>
                                        <input type="text" name="dose" class="form-control form-control-sm @error('dose') is-invalid @enderror" value="{{ old('dose') }}" maxlength="60" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Vía <span class="text-danger">*</span></label>
                                        <select name="route" class="form-select form-select-sm">
                                            @foreach(\App\Models\PrescriptionItem::ROUTES as $value => $label)
                                                <option value="{{ $value }}" @selected(old('route') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Cada (horas) <span class="text-danger">*</span></label>
                                        <input type="number" name="frequency_hours" min="1" max="168" class="form-control form-control-sm @error('frequency_hours') is-invalid @enderror" value="{{ old('frequency_hours', 12) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Primera toma <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="starts_at" class="form-control form-control-sm @error('starts_at') is-invalid @enderror" value="{{ old('starts_at', $nowInput) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Hasta <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="ends_at" class="form-control form-control-sm @error('ends_at') is-invalid @enderror" value="{{ old('ends_at', now()->addDays(3)->format('Y-m-d\TH:i')) }}" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Indicación</label>
                                        <input type="text" name="instructions" class="form-control form-control-sm" value="{{ old('instructions') }}" maxlength="255">
                                    </div>
                                    <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check-lg"></i> Programar</button></div>
                                </form>
                            </details>
                        @endif

                        <h6 class="fw-bold mt-3"><i class="bi bi-alarm"></i> Horario de administración</h6>
                        <div class="table-responsive" style="max-height: 28rem; overflow-y: auto;">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Hora</th><th>Medicamento</th><th>Estado</th><th>Registró</th><th></th></tr></thead>
                                <tbody>
                                    @forelse($doses as $dose)
                                        <tr class="{{ $dose->isOverdue() ? 'table-danger' : '' }}">
                                            <td class="text-nowrap small fw-semibold">{{ $dose->scheduled_at->format('d/m H:i') }}</td>
                                            <td class="small">{{ $dose->treatment->medication }} · {{ $dose->treatment->dose }}</td>
                                            <td>
                                                <span class="badge bg-{{ $dose->status_color }}">{{ $dose->isOverdue() ? 'Atrasada' : $dose->status_label }}</span>
                                                @if($dose->notes)<div class="small text-muted">{{ $dose->notes }}</div>@endif
                                            </td>
                                            <td class="small text-muted">{{ $dose->doneBy?->name }}{{ $dose->done_at ? ' · ' . $dose->done_at->format('d/m H:i') : '' }}</td>
                                            <td class="text-end text-nowrap">
                                                @if($canEdit && $dose->status === 'pending')
                                                    <form action="{{ route('hospitalizations.doses.give', $dose) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-success" type="submit" title="Aplicar"><i class="bi bi-check2"></i></button>
                                                    </form>
                                                    <form action="{{ route('hospitalizations.doses.skip', $dose) }}" method="POST" class="d-inline dose-skip">
                                                        @csrf
                                                        <input type="hidden" name="notes">
                                                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="Omitir"><i class="bi bi-slash-circle"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-3">Sin tratamientos programados.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Cuidados y evolución --}}
                    <div class="tab-pane fade {{ $tab === 'records' ? 'show active' : '' }}" id="tab-records">
                        @if($canEdit)
                            <form action="{{ route('hospitalizations.records.store', $h) }}" method="POST" class="border rounded p-3 mb-3 row g-2">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Tipo</label>
                                    <select name="type" class="form-select form-select-sm">
                                        @foreach(\App\Models\HospitalizationRecord::TYPES as $value => [$label])
                                            <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Fecha y hora</label>
                                    <input type="datetime-local" name="recorded_at" class="form-control form-control-sm @error('recorded_at') is-invalid @enderror" value="{{ old('recorded_at', $nowInput) }}" max="{{ $nowInput }}" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Estado del paciente</label>
                                    <select name="condition" class="form-select form-select-sm">
                                        <option value="">Sin cambios</option>
                                        @foreach(\App\Models\Hospitalization::CONDITIONS as $value => [$label])
                                            <option value="{{ $value }}" @selected(old('condition') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">Temp. °C</label><input type="number" step="0.1" name="temperature" class="form-control form-control-sm @error('temperature') is-invalid @enderror" value="{{ old('temperature') }}"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">FC (lpm)</label><input type="number" name="heart_rate" class="form-control form-control-sm @error('heart_rate') is-invalid @enderror" value="{{ old('heart_rate') }}"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">FR (rpm)</label><input type="number" name="respiratory_rate" class="form-control form-control-sm @error('respiratory_rate') is-invalid @enderror" value="{{ old('respiratory_rate') }}"></div>
                                <div class="col-6 col-md-2"><label class="form-label small mb-1">Peso (kg)</label><input type="number" step="0.01" name="weight" class="form-control form-control-sm @error('weight') is-invalid @enderror" value="{{ old('weight') }}"></div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Hidratación</label>
                                    <select name="hydration" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach(\App\Models\Consultation::HYDRATION as $value => $label)
                                            <option value="{{ $value }}" @selected(old('hydration') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <textarea name="notes" rows="2" class="form-control form-control-sm @error('notes') is-invalid @enderror" placeholder="Observaciones, cambios clínicos, lo que comió, incidencias…">{{ old('notes') }}</textarea>
                                </div>
                                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Agregar registro</button></div>
                            </form>
                        @endif

                        @forelse($h->records as $record)
                            @php [$typeLabel, $icon, $color] = \App\Models\HospitalizationRecord::TYPES[$record->type] ?? [$record->type, 'bi-dot', 'secondary']; @endphp
                            <div class="border-start border-3 border-{{ $color }} ps-2 mb-3 small">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold"><i class="bi {{ $icon }}"></i> {{ $typeLabel }}</span>
                                    <span class="text-muted">{{ $record->recorded_at->format('d/m/Y H:i') }} · {{ $record->recorder?->name }}</span>
                                </div>
                                @php
                                    $values = array_filter([
                                        $record->temperature !== null ? "{$record->temperature} °C" : null,
                                        $record->heart_rate ? "FC {$record->heart_rate}" : null,
                                        $record->respiratory_rate ? "FR {$record->respiratory_rate}" : null,
                                        $record->weight !== null ? $num($record->weight) . ' kg' : null,
                                        \App\Models\Consultation::HYDRATION[$record->hydration] ?? null,
                                    ]);
                                @endphp
                                @if($values)<div>{{ implode(' · ', $values) }}</div>@endif
                                @if($record->condition)<span class="badge bg-{{ \App\Models\Hospitalization::CONDITIONS[$record->condition][1] ?? 'secondary' }}">{{ \App\Models\Hospitalization::CONDITIONS[$record->condition][0] ?? $record->condition }}</span>@endif
                                @if($record->notes)<div style="white-space: pre-line">{{ $record->notes }}</div>@endif
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Sin registros todavía.</p>
                        @endforelse
                    </div>

                    {{-- Costos --}}
                    <div class="tab-pane fade {{ $tab === 'costs' ? 'show active' : '' }}" id="tab-costs">
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>Concepto</th><th class="text-end">Cant.</th><th class="text-end">Precio</th><th class="text-end">Total</th><th></th></tr></thead>
                            <tbody>
                                <tr>
                                    <td>Estadía</td>
                                    <td class="text-end">{{ $h->stayDays() }}</td>
                                    <td class="text-end">{{ $money($h->daily_rate) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($h->stayCost()) }}</td>
                                    <td></td>
                                </tr>
                                @foreach($h->charges as $charge)
                                    <tr>
                                        <td class="small">{{ $charge->description }}</td>
                                        <td class="text-end">{{ $num($charge->quantity) }}</td>
                                        <td class="text-end">{{ $money($charge->unit_price) }}</td>
                                        <td class="text-end fw-semibold">{{ $money($charge->total) }}</td>
                                        <td class="text-end">
                                            @if($canEdit && ! $charge->hospitalization_dose_id)
                                                <form action="{{ route('hospitalizations.charges.destroy', [$h, $charge]) }}" method="POST" class="m-0" onsubmit="return confirm('¿Quitar este cargo?')">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit" title="Quitar"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light"><tr class="fs-6"><td colspan="3" class="text-end fw-bold">Total</td><td class="text-end fw-bold">{{ $money($h->stayCost() + $chargesTotal) }}</td><td></td></tr></tfoot>
                        </table>
                        @if($h->status !== 'cancelled' && $tenantCompany?->planAllows('pos'))
                            @if($h->sale_id)
                                <p class="small mb-3">
                                    <i class="bi bi-check-circle text-success"></i> Cobrada
                                    @if($can('sales.view'))en la <a href="{{ route('sales.show', $h->sale_id) }}" class="text-decoration-none">venta {{ $h->sale?->code }}</a>@endif.
                                </p>
                            @elseif($h->isActive())
                                <p class="small text-muted">Se cobra en el punto de venta después del alta, con el total definitivo.</p>
                            @elseif($can('sales.create') && $h->stayCost() + $chargesTotal > 0)
                                <a href="{{ route('pos.create', ['pet_id' => $pet->id]) }}" class="btn btn-success btn-sm mb-3"><i class="bi bi-cash-coin"></i> Cobrar en el punto de venta</a>
                            @endif
                        @endif
                        @if($canEdit)
                            <form action="{{ route('hospitalizations.charges.store', $h) }}" method="POST" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-6"><label class="form-label small mb-1">Concepto</label><input type="text" name="description" class="form-control form-control-sm @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="255" required></div>
                                <div class="col-md-2"><label class="form-label small mb-1">Cant.</label><input type="number" name="quantity" step="0.01" min="0.01" class="form-control form-control-sm @error('quantity') is-invalid @enderror" value="{{ old('quantity', 1) }}" required></div>
                                <div class="col-md-2"><label class="form-label small mb-1">Precio</label><input type="number" name="unit_price" step="0.01" min="0" class="form-control form-control-sm @error('unit_price') is-invalid @enderror" value="{{ old('unit_price') }}" required></div>
                                <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Agregar</button></div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> Admisión</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Ingreso</dt><dd class="col-7">{{ $h->admitted_at->format('d/m/Y H:i') }}</dd>
                        <dt class="col-5 text-muted fw-normal">Tiempo</dt><dd class="col-7">{{ $h->stayDays() }} {{ $h->stayDays() === 1 ? 'día' : 'días' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Motivo</dt><dd class="col-7">{{ $h->reason }}</dd>
                        <dt class="col-5 text-muted fw-normal">Diagnóstico</dt><dd class="col-7">{{ $h->admission_diagnosis ?: '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Responsable</dt><dd class="col-7">{{ $h->veterinarian?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Ubicación</dt><dd class="col-7">{{ $h->kennel?->name ?? 'Sin asignar' }}</dd>
                        @if($h->consultation && $can('consultations.view'))
                            <dt class="col-5 text-muted fw-normal">Consulta</dt>
                            <dd class="col-7"><a href="{{ route('consultations.show', $h->consultation) }}" class="text-decoration-none">{{ $h->consultation->started_at->format('d/m/Y') }}</a></dd>
                        @endif
                    </dl>
                    @if($h->notes)<p class="small mt-2 mb-0" style="white-space: pre-line">{{ $h->notes }}</p>@endif
                    @if($canEdit)
                        <form action="{{ route('hospitalizations.move', $h) }}" method="POST" class="d-flex gap-2 mt-3">
                            @csrf
                            <select name="kennel_id" class="form-select form-select-sm @error('kennel_id') is-invalid @enderror">
                                <option value="">Sin asignar</option>
                                @foreach($kennels as $kennel)
                                    <option value="{{ $kennel->id }}" @selected($h->kennel_id === $kennel->id)>{{ $kennel->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-secondary text-nowrap" type="submit">Mover</button>
                        </form>
                    @endif
                </div>
            </div>

            @if($latestVitals)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body small">
                        <h6 class="fw-bold mb-2"><i class="bi bi-heart-pulse"></i> Últimos signos vitales</h6>
                        <div>
                            {{ implode(' · ', array_filter([
                                $latestVitals->temperature !== null ? "{$latestVitals->temperature} °C" : null,
                                $latestVitals->heart_rate ? "FC {$latestVitals->heart_rate}" : null,
                                $latestVitals->respiratory_rate ? "FR {$latestVitals->respiratory_rate}" : null,
                                $latestVitals->weight !== null ? $num($latestVitals->weight) . ' kg' : null,
                            ])) }}
                        </div>
                        <div class="text-muted">{{ $latestVitals->recorded_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            @endif

            @if($h->discharged_at)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body small">
                        <h6 class="fw-bold mb-2"><i class="bi bi-box-arrow-right"></i> {{ $h->status === 'deceased' ? 'Fallecimiento' : 'Alta médica' }}</h6>
                        <div>{{ $h->discharged_at->format('d/m/Y H:i') }}{{ $h->discharger ? ' · ' . $h->discharger->name : '' }}</div>
                        <div class="mt-1"><span class="text-muted">Diagnóstico final:</span> {{ $h->final_diagnosis }}</div>
                        @foreach(['home_treatment' => 'Tratamiento domiciliario', 'discharge_instructions' => 'Indicaciones', 'summary' => 'Resumen'] as $field => $label)
                            @if($h->$field)
                                <div class="text-muted mt-2">{{ $label }}</div>
                                <div style="white-space: pre-line">{{ $h->$field }}</div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if($canClose)
                <details class="card border-0 shadow-sm mb-3" {{ $errors->hasAny(['discharged_at', 'final_diagnosis', 'outcome']) ? 'open' : '' }}>
                    <summary class="card-body fw-semibold text-success"><i class="bi bi-box-arrow-right"></i> Dar de alta</summary>
                    <form action="{{ route('hospitalizations.discharge', $h) }}" method="POST" class="card-body pt-0 d-flex flex-column gap-2" onsubmit="return confirm('Al cerrar, se cancelan las tomas pendientes, se libera el espacio y la internación ya no admite cambios. ¿Continuar?')">
                        @csrf
                        <select name="outcome" class="form-select form-select-sm">
                            <option value="discharged" @selected(old('outcome') === 'discharged')>Alta médica</option>
                            <option value="deceased" @selected(old('outcome') === 'deceased')>Fallecimiento</option>
                        </select>
                        <input type="datetime-local" name="discharged_at" class="form-control form-control-sm @error('discharged_at') is-invalid @enderror" value="{{ old('discharged_at', $nowInput) }}" max="{{ $nowInput }}" required>
                        <input type="text" name="final_diagnosis" class="form-control form-control-sm @error('final_diagnosis') is-invalid @enderror" placeholder="Diagnóstico final (obligatorio)" value="{{ old('final_diagnosis', $h->admission_diagnosis) }}" maxlength="255" required>
                        <textarea name="home_treatment" rows="2" class="form-control form-control-sm" placeholder="Tratamiento domiciliario">{{ old('home_treatment') }}</textarea>
                        <textarea name="discharge_instructions" rows="2" class="form-control form-control-sm" placeholder="Indicaciones al propietario">{{ old('discharge_instructions') }}</textarea>
                        <textarea name="summary" rows="2" class="form-control form-control-sm" placeholder="Resumen de la hospitalización">{{ old('summary') }}</textarea>
                        <button class="btn btn-sm btn-success" type="submit">Cerrar internación</button>
                    </form>
                </details>

                @if($h->records->isEmpty() && ! $doses->contains('status', 'given'))
                    <details class="card border-0 shadow-sm">
                        <summary class="card-body text-danger fw-semibold"><i class="bi bi-x-octagon"></i> Anular ingreso</summary>
                        <form action="{{ route('hospitalizations.cancel', $h) }}" method="POST" class="card-body pt-0 d-flex flex-column gap-2" onsubmit="return confirm('¿Anular este ingreso?')">
                            @csrf
                            <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Motivo (obligatorio)" maxlength="255" required>
                            <button class="btn btn-sm btn-danger" type="submit">Anular</button>
                        </form>
                    </details>
                @endif
            @endif
        </div>
    </div>
</div>

<script>
    // Omitir una toma exige dejar el motivo.
    document.querySelectorAll('.dose-skip').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const reason = window.prompt('¿Por qué se omite esta toma?');
            if (!reason || !reason.trim()) { event.preventDefault(); return; }
            form.querySelector('[name="notes"]').value = reason.trim();
        });
    });
</script>
@endsection
