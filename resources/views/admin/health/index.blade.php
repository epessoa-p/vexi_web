@extends('layouts.app')

@section('title', 'Vacunación')

@section('page')
@php
    $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany);
    $link = fn (array $params) => route('health.index', array_filter(array_merge(['tab' => $tab, 'kind' => $kind, 'days' => $days], $params)));
    $today = now()->startOfDay();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-shield-plus"></i> Vacunación y desparasitación</h1>
            <p class="text-muted mb-0">Calendario sanitario de los pacientes activos.</p>
        </div>
        @if($can('vaccinations.create'))
            <div class="d-flex gap-2">
                <a href="{{ route('vaccinations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Aplicar vacuna</a>
                <a href="{{ route('dewormings.create') }}" class="btn btn-outline-primary"><i class="bi bi-plus-lg"></i> Desparasitar</a>
            </div>
        @endif
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <ul class="nav nav-pills">
            <li class="nav-item"><a class="nav-link {{ $tab === 'overdue' ? 'active' : '' }}" href="{{ $link(['tab' => 'overdue']) }}">Atrasadas <span class="badge bg-danger">{{ $counts['overdue'] }}</span></a></li>
            <li class="nav-item"><a class="nav-link {{ $tab === 'upcoming' ? 'active' : '' }}" href="{{ $link(['tab' => 'upcoming']) }}">Próximas {{ $days }} días <span class="badge bg-warning text-dark">{{ $counts['upcoming'] }}</span></a></li>
            <li class="nav-item"><a class="nav-link {{ $tab === 'history' ? 'active' : '' }}" href="{{ $link(['tab' => 'history']) }}">Aplicadas (60 días)</a></li>
        </ul>
        <div class="d-flex gap-2 align-items-center">
            <div class="btn-group btn-group-sm">
                <a href="{{ $link(['kind' => null]) }}" class="btn {{ ! $kind ? 'btn-dark' : 'btn-outline-dark' }}">Todo</a>
                <a href="{{ $link(['kind' => 'vaccines']) }}" class="btn {{ $kind === 'vaccines' ? 'btn-dark' : 'btn-outline-dark' }}">Vacunas</a>
                <a href="{{ $link(['kind' => 'dewormings']) }}" class="btn {{ $kind === 'dewormings' ? 'btn-dark' : 'btn-outline-dark' }}">Desparasitaciones</a>
            </div>
            @if($tab === 'upcoming')
                <form method="GET" class="d-flex gap-1 align-items-center">
                    <input type="hidden" name="tab" value="upcoming">
                    @if($kind)<input type="hidden" name="kind" value="{{ $kind }}">@endif
                    <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach([7, 15, 30, 60, 90] as $option)
                            <option value="{{ $option }}" @selected($days === $option)>{{ $option }} días</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">{{ $tab === 'history' ? 'Aplicada' : 'Corresponde' }}</th>
                            <th>Paciente</th>
                            <th>Vacuna / desparasitación</th>
                            <th>Propietario</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php
                                $pet = $item['pet'];
                                $client = $pet?->client;
                                $date = $tab === 'history' ? $item['applied'] : $item['due'];
                                $diff = (int) $today->diffInDays($item['due'] ?? $today, false);
                                $reminder = null;
                                if ($tab !== 'history' && $client?->whatsapp_url) {
                                    $text = sprintf('Hola %s, le recordamos que %s tiene pendiente: %s (%s). Agende su visita respondiendo este mensaje. %s',
                                        $client->first_name, $pet->name, $item['title'], $item['due']->format('d/m/Y'), $tenantCompany?->name);
                                    $reminder = $client->whatsapp_url . '?text=' . rawurlencode($text);
                                }
                            @endphp
                            <tr>
                                <td class="ps-3 text-nowrap">
                                    <div class="fw-semibold">{{ $date?->format('d/m/Y') }}</div>
                                    @if($tab === 'overdue')
                                        <span class="badge bg-danger">hace {{ abs($diff) }} {{ abs($diff) === 1 ? 'día' : 'días' }}</span>
                                    @elseif($tab === 'upcoming')
                                        <span class="badge {{ $diff <= 3 ? 'bg-warning text-dark' : 'bg-light text-dark border' }}">{{ $diff === 0 ? 'hoy' : "en $diff " . ($diff === 1 ? 'día' : 'días') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($pet)
                                        <a href="{{ route('pets.show', ['pet' => $pet, 'tab' => 'health']) }}" class="fw-semibold text-decoration-none">{{ $pet->name }}</a>
                                        <div class="small text-muted">{{ $pet->species?->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <i class="bi {{ $item['type'] === 'vaccine' ? 'bi-shield-plus text-success' : 'bi-bug text-primary' }}"></i>
                                    {{ $item['title'] }}
                                    <div class="small text-muted">{{ $item['detail'] }}{{ $tab === 'history' && $item['veterinarian'] ? ' · ' . $item['veterinarian'] : '' }}</div>
                                </td>
                                <td>
                                    {{ $client?->full_name }}
                                    @if($client?->phone)<div class="small text-muted">{{ $client->phone }}</div>@endif
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($reminder)
                                        <a href="{{ $reminder }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Recordar por WhatsApp"><i class="bi bi-whatsapp"></i></a>
                                    @endif
                                    @if($tab !== 'history' && $can('vaccinations.create') && $pet)
                                        @if($item['type'] === 'vaccine')
                                            <a href="{{ route('vaccinations.create', ['pet_id' => $pet->id, 'vaccine_id' => $item['record']->vaccine_id]) }}" class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Aplicar</a>
                                        @else
                                            <a href="{{ route('dewormings.create', ['pet_id' => $pet->id, 'type' => $item['record']->type]) }}" class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Aplicar</a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">
                                {{ match ($tab) { 'overdue' => 'No hay vacunas ni desparasitaciones atrasadas.', 'upcoming' => "Nada programado en los próximos $days días.", default => 'Sin aplicaciones en los últimos 60 días.' } }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
