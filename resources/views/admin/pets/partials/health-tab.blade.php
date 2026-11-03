{{-- Pestaña "Vacunas" de la ficha: pendientes, vacunas y desparasitaciones. Usa $pet, $vaccinations, $dewormings, $pendingHealth, $can, $canHealth, $tab. --}}
@php $today = now()->startOfDay(); @endphp
<div class="tab-pane fade {{ $tab === 'health' ? 'show active' : '' }}" id="tab-health">
    <div class="d-flex flex-wrap gap-2 mb-3">
        @if($canHealth)
            <a href="{{ route('vaccinations.create', ['pet_id' => $pet->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Aplicar vacuna</a>
            <a href="{{ route('dewormings.create', ['pet_id' => $pet->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-lg"></i> Desparasitar</a>
        @endif
        <a href="{{ route('health.card', $pet) }}" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-printer"></i> Carnet sanitario</a>
    </div>

    @if($pendingHealth->isNotEmpty())
        <div class="mb-3">
            @foreach($pendingHealth as $pending)
                @php $late = $pending['due']->lt($today); @endphp
                <span class="badge {{ $late ? 'bg-danger' : 'bg-light text-dark border' }} me-1 mb-1">
                    {{ $pending['title'] }}: {{ $pending['due']->format('d/m/Y') }}{{ $late ? ' (atrasada)' : '' }}
                </span>
            @endforeach
        </div>
    @endif

    <h6 class="fw-bold mt-2"><i class="bi bi-shield-plus"></i> Vacunas</h6>
    <div class="table-responsive mb-4">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Vacuna</th><th>Dosis</th><th>Lote</th><th>Responsable</th><th>Próxima</th><th></th></tr></thead>
            <tbody>
                @forelse($vaccinations as $vaccination)
                    <tr>
                        <td>{{ $vaccination->applied_at->format('d/m/Y') }}</td>
                        <td>{{ $vaccination->vaccine?->name }}</td>
                        <td>{{ $vaccination->dose_number }}</td>
                        <td class="small">{{ $vaccination->lot ?: '—' }}@if($vaccination->lot_expires_at)<div class="text-muted">vence {{ $vaccination->lot_expires_at->format('m/Y') }}</div>@endif</td>
                        <td class="small">{{ $vaccination->veterinarian?->name ?? '—' }}</td>
                        <td>{{ $vaccination->next_due_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            @if($can('vaccinations.edit'))
                                <a href="{{ route('vaccinations.edit', $vaccination) }}" class="btn btn-sm btn-link p-0 me-2" title="Editar"><i class="bi bi-pencil"></i></a>
                            @endif
                            @if($can('vaccinations.delete'))
                                <form action="{{ route('vaccinations.destroy', $vaccination) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este registro de vacuna?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit" title="Eliminar"><i class="bi bi-x-circle"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin vacunas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h6 class="fw-bold"><i class="bi bi-bug"></i> Desparasitaciones</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Dosis</th><th>Responsable</th><th>Próxima</th><th></th></tr></thead>
            <tbody>
                @forelse($dewormings as $deworming)
                    <tr>
                        <td>{{ $deworming->applied_at->format('d/m/Y') }}</td>
                        <td>{{ $deworming->type_label }}</td>
                        <td>{{ $deworming->product }}</td>
                        <td class="small">{{ $deworming->dose ?: '—' }}{{ $deworming->weight ? ' · ' . rtrim(rtrim(number_format($deworming->weight, 2), '0'), '.') . ' kg' : '' }}</td>
                        <td class="small">{{ $deworming->veterinarian?->name ?? '—' }}</td>
                        <td>{{ $deworming->next_due_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            @if($can('vaccinations.edit'))
                                <a href="{{ route('dewormings.edit', $deworming) }}" class="btn btn-sm btn-link p-0 me-2" title="Editar"><i class="bi bi-pencil"></i></a>
                            @endif
                            @if($can('vaccinations.delete'))
                                <form action="{{ route('dewormings.destroy', $deworming) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este registro de desparasitación?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit" title="Eliminar"><i class="bi bi-x-circle"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin desparasitaciones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
