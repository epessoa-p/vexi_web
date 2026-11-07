@extends('layouts.app')

@section('title', "Estudio {$labRequest->code}")

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $l = $labRequest;
    $pet = $l->pet;
    $canResults = $l->isEditable() && $can('lab_results.edit');
    $canStatus = $can('lab_requests.edit');
    $rows = old('results', $rows);
    $flagStyles = ['low' => ['Bajo', 'text-primary'], 'high' => ['Alto', 'text-danger'], 'normal' => ['Normal', 'text-success']];
@endphp
<div class="container-fluid" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @include('admin.pets.partials.avatar', ['pet' => $pet, 'size' => 56])
            <div>
                <h1 class="mb-0 h3">{{ $l->studyType?->name }} <span class="badge bg-{{ $l->status_color }} fs-6 align-middle">{{ $l->status_label }}</span></h1>
                <div class="text-muted small">
                    {{ $l->code }} ·
                    <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'record']) }}" class="text-decoration-none">{{ $pet->name }}</a>
                    · {{ $pet->species?->name }}{{ $pet->breed ? ' · ' . $pet->breed->name : '' }} · {{ $pet->client?->full_name }}
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($canStatus && $l->canTransition('start'))
                <form action="{{ route('lab.transition', [$l, 'start']) }}" method="POST" class="m-0">@csrf
                    <button class="btn btn-info" type="submit"><i class="bi bi-play-fill"></i> En proceso</button>
                </form>
            @endif
            @if($canStatus && $l->canTransition('deliver'))
                <form action="{{ route('lab.transition', [$l, 'deliver']) }}" method="POST" class="m-0">@csrf
                    <button class="btn btn-primary" type="submit"><i class="bi bi-send-check"></i> Marcar entregado</button>
                </form>
            @endif
            @if(in_array($l->status, ['completed', 'delivered'], true))
                <a href="{{ route('lab.report', $l) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Informe</a>
            @endif
            @if($tenantCompany?->planAllows('pos'))
                @if($l->sale_id && $can('sales.view'))
                    <a href="{{ route('sales.show', $l->sale_id) }}" class="btn btn-outline-success"><i class="bi bi-receipt"></i> Cobrado</a>
                @elseif(! $l->sale_id && $l->status !== 'cancelled' && $can('sales.create'))
                    <a href="{{ route('pos.create', ['pet_id' => $pet->id]) }}" class="btn btn-success"><i class="bi bi-cash-coin"></i> Cobrar</a>
                @endif
            @endif
            <a href="{{ route('lab.index') }}" class="btn btn-outline-secondary">Laboratorio</a>
        </div>
    </div>

    @if($l->status === 'cancelled')
        <div class="alert alert-dark"><i class="bi bi-x-octagon"></i> Anulado el {{ $l->cancelled_at->format('d/m/Y H:i') }}: {{ $l->cancel_reason }}</div>
        @if($l->sale)
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Este estudio ya se había cobrado en la venta {{ $l->sale->code }}. Si no corresponde mantener el cobro, registra allí la devolución.</div>
        @endif
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Resultados --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clipboard2-data"></i> Resultados</h6>
                    @if($canResults)
                        <form action="{{ route('lab.results.save', $l) }}" method="POST">
                            @csrf
                            @method('PUT')
                            @error('results')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light"><tr><th style="min-width: 10rem">Parámetro</th><th style="min-width: 7rem">Valor</th><th style="width: 7rem">Unidad</th><th style="width: 6rem">Ref. mín.</th><th style="width: 6rem">Ref. máx.</th><th style="min-width: 8rem">Ref. texto</th><th></th></tr></thead>
                                    <tbody id="result-rows" data-next="{{ count($rows) }}">
                                        @foreach($rows as $i => $row)
                                            @include('admin.lab.partials.result-row', ['i' => $i, 'row' => $row])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="add-result"><i class="bi bi-plus-lg"></i> Agregar parámetro</button>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Fecha de resultado</label>
                                    <input type="date" name="result_date" class="form-control @error('result_date') is-invalid @enderror" value="{{ old('result_date', $l->result_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Profesional responsable</label>
                                    <select name="responsible_id" class="form-select @error('responsible_id') is-invalid @enderror">
                                        @foreach($professionals as $professional)
                                            <option value="{{ $professional->id }}" @selected((string) old('responsible_id', $l->responsible_id ?? auth()->id()) === (string) $professional->id)>{{ $professional->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Observaciones / informe</label>
                                    <textarea name="result_notes" rows="3" class="form-control">{{ old('result_notes', $l->result_notes) }}</textarea>
                                </div>
                                <div class="col-12 d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
                                    <button class="btn btn-success" type="submit" name="finish" value="1" onclick="return confirm('Al completar, los resultados quedan cerrados y ya no se pueden editar. ¿Continuar?')">
                                        <i class="bi bi-check2-circle"></i> Guardar y completar
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        @if($l->results->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light"><tr><th>Parámetro</th><th class="text-end">Valor</th><th>Unidad</th><th>Referencia</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach($l->results as $result)
                                            <tr>
                                                <td>{{ $result->name }}</td>
                                                <td class="text-end fw-semibold {{ $flagStyles[$result->flag][1] ?? '' }}">{{ $result->value ?: '—' }}</td>
                                                <td class="small text-muted">{{ $result->unit }}</td>
                                                <td class="small text-muted">{{ $result->reference_label ?: '—' }}</td>
                                                <td class="small {{ $flagStyles[$result->flag][1] ?? '' }}">{{ in_array($result->flag, ['low', 'high'], true) ? $flagStyles[$result->flag][0] : '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif(! $l->result_notes)
                            <p class="text-muted small mb-0">Sin resultados registrados.</p>
                        @endif
                        @if($l->result_notes)
                            <div class="small text-muted">Observaciones</div>
                            <p class="mb-2" style="white-space: pre-line">{{ $l->result_notes }}</p>
                        @endif
                        @if($l->result_date)
                            <p class="small text-muted mb-0">Resultado del {{ $l->result_date->format('d/m/Y') }}{{ $l->responsible ? ' · ' . $l->responsible->name : '' }}</p>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Adjuntos --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-paperclip"></i> Documentos e imágenes</h6>
                    <div class="row g-2 mb-2">
                        @forelse($l->attachments as $attachment)
                            <div class="col-md-6">
                                <div class="border rounded p-2 d-flex align-items-center gap-2">
                                    @if($attachment->isImage())
                                        <img src="{{ route('lab.attachments.show', [$l, $attachment]) }}" alt="" class="rounded object-fit-cover" style="width: 48px; height: 48px;">
                                    @else
                                        <i class="bi bi-file-earmark-pdf fs-3 text-danger"></i>
                                    @endif
                                    <a href="{{ route('lab.attachments.show', [$l, $attachment]) }}" target="_blank" class="text-decoration-none text-truncate flex-grow-1 small">{{ $attachment->original_name }}</a>
                                    @if($canResults)
                                        <form action="{{ route('lab.attachments.destroy', [$l, $attachment]) }}" method="POST" class="m-0" onsubmit="return confirm('¿Eliminar este archivo?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0" type="submit" title="Eliminar"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-12 small text-muted">Sin archivos adjuntos.</div>
                        @endforelse
                    </div>
                    @if($canResults)
                        <form action="{{ route('lab.attachments.store', $l) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="form-control form-control-sm @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror" required>
                            <button class="btn btn-sm btn-outline-primary text-nowrap" type="submit"><i class="bi bi-upload"></i> Adjuntar</button>
                        </form>
                        <div class="form-text">Imágenes o PDF, hasta 10 MB cada uno.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle"></i> Solicitud</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted fw-normal">Fecha</dt><dd class="col-7">{{ $l->requested_at->format('d/m/Y H:i') }}</dd>
                        <dt class="col-5 text-muted fw-normal">Solicitó</dt><dd class="col-7">{{ $l->requester?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Categoría</dt><dd class="col-7">{{ $l->studyType?->category_label }}</dd>
                        @if($l->external_lab)
                            <dt class="col-5 text-muted fw-normal">Laboratorio</dt><dd class="col-7">{{ $l->external_lab }}</dd>
                        @endif
                        <dt class="col-5 text-muted fw-normal">Consulta</dt>
                        <dd class="col-7">
                            @if($l->consultation && $can('consultations.view'))
                                <a href="{{ route('consultations.show', $l->consultation) }}" class="text-decoration-none">{{ $l->consultation->started_at->format('d/m/Y') }}</a>
                            @else
                                {{ $l->consultation?->started_at->format('d/m/Y') ?? '—' }}
                            @endif
                        </dd>
                    </dl>
                    @if($l->notes)
                        <hr>
                        <div class="small text-muted mb-1">Observaciones</div>
                        <p class="small mb-0" style="white-space: pre-line">{{ $l->notes }}</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-clock-history"></i> Seguimiento</h6>
                    <ul class="list-unstyled small mb-0">
                        @foreach(['requested_at' => 'Solicitado', 'started_at' => 'En proceso', 'completed_at' => 'Completado', 'delivered_at' => 'Entregado', 'cancelled_at' => 'Anulado'] as $field => $label)
                            @if($l->$field)
                                <li class="mb-1"><i class="bi bi-check2 text-success"></i> {{ $label }}: {{ $l->$field->format('d/m/Y H:i') }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>

            @if($canStatus && $l->canTransition('cancel'))
                <details class="card border-0 shadow-sm">
                    <summary class="card-body text-danger fw-semibold"><i class="bi bi-x-octagon"></i> Anular estudio</summary>
                    <form action="{{ route('lab.transition', [$l, 'cancel']) }}" method="POST" class="card-body pt-0 d-flex flex-column gap-2" onsubmit="return confirm('¿Anular este estudio?')">
                        @csrf
                        <input type="text" name="cancel_reason" class="form-control form-control-sm" placeholder="Motivo (obligatorio)" maxlength="255" required>
                        <button class="btn btn-sm btn-danger" type="submit">Anular</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</div>

@if($canResults)
    <template id="result-template">@include('admin.lab.partials.result-row', ['i' => '__i__', 'row' => []])</template>
    <script>
    (function () {
        const rows = document.getElementById('result-rows');
        document.getElementById('add-result').addEventListener('click', function () {
            const index = parseInt(rows.dataset.next, 10);
            rows.dataset.next = index + 1;
            rows.insertAdjacentHTML('beforeend', document.getElementById('result-template').innerHTML.replaceAll('__i__', index));
            rows.lastElementChild.querySelector('input').focus();
        });
        rows.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-remove-row]');
            if (remove) remove.closest('.result-row').remove();
        });
    })();
    </script>
@endif
@endsection
