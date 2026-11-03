@extends('layouts.app')

@section('title', 'Agenda')

@push('styles')
<style>
    #calendar { --fc-today-bg-color: rgba(13, 110, 253, .06); min-height: 70vh; }
    #calendar .fc-event { cursor: pointer; }
    #calendar .fc-status-cancelled, #calendar .fc-status-no_show { opacity: .45; text-decoration: line-through; }
    #calendar .fc-status-attended { opacity: .7; }
    #calendar .fc-status-pending .fc-event-main::before { content: '○ '; }
    #calendar .fc-status-confirmed .fc-event-main::before { content: '✓ '; }
    #calendar .fc-status-waiting .fc-event-main::before { content: '⏳ '; }
    #calendar .fc-status-in_progress .fc-event-main::before { content: '▶ '; }
</style>
@endpush

@section('page')
@php $can = fn (string $permission) => auth()->user()->hasPermissionInCompany($permission, $tenantCompany); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-calendar-week"></i> Agenda</h1>
            <p class="text-muted mb-0">
                Haz clic en un horario libre para agendar{{ $can('appointments.edit') ? ' y arrastra una cita para reprogramarla' : '' }}.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('waiting-room.index') }}" class="btn btn-outline-secondary"><i class="bi bi-hourglass-split"></i> Sala de espera</a>
            @if($can('appointments.create'))
                <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva cita</a>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end" id="calendar-filters">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Profesional</label>
                <select name="veterinarian_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($professionals as $professional)
                        <option value="{{ $professional->id }}" @selected((string) request('veterinarian_id') === (string) $professional->id)>{{ $professional->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($branches->count() > 1)
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Sucursal</label>
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Servicio</label>
                <select name="service_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" name="show_cancelled" value="1" id="show-cancelled">
                    <label class="form-check-label small" for="show-cancelled">Mostrar canceladas / no asistió</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div id="calendar"></div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="calendar-toast" class="toast align-items-center border-0" role="alert">
        <div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const eventsUrl = @json(route('appointments.events'));
    const createUrl = @json($can('appointments.create') ? route('appointments.create') : null);
    const moveUrl = @json(route('appointments.move', ['appointment' => '__ID__']));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const filters = document.getElementById('calendar-filters');

    // Fecha/hora "de pared": el calendario va en UTC para no aplicar la zona del navegador.
    const wall = (date) => date.toISOString().slice(0, 19);

    function notify(message, ok) {
        const toast = document.getElementById('calendar-toast');
        toast.className = 'toast align-items-center border-0 text-white ' + (ok ? 'bg-success' : 'bg-danger');
        toast.querySelector('.toast-body').textContent = message;
        bootstrap.Toast.getOrCreateInstance(toast).show();
    }

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        locale: 'es',
        timeZone: 'UTC',
        initialView: window.innerWidth < 768 ? 'timeGridDay' : 'timeGridWeek',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridDay,timeGridWeek,dayGridMonth,listWeek' },
        buttonText: { listWeek: 'lista' },
        slotMinTime: @json(config('vexi.agenda.slot_min_time')),
        slotMaxTime: @json(config('vexi.agenda.slot_max_time')),
        slotDuration: '00:15:00',
        slotLabelInterval: '01:00',
        nowIndicator: true,
        allDaySlot: false,
        height: 'auto',
        expandRows: true,
        dayMaxEvents: 4,
        now: @json(now()->format('Y-m-d\TH:i:s')),
        selectable: !!createUrl,
        events: function (info, success, failure) {
            const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
            filters.querySelectorAll('select, input').forEach(function (field) {
                if (field.type === 'checkbox' ? field.checked : field.value) params.set(field.name, field.type === 'checkbox' ? '1' : field.value);
            });
            fetch(eventsUrl + '?' + params, { headers: { 'Accept': 'application/json' } })
                .then((r) => r.json()).then(success).catch(failure);
        },
        eventDidMount: function (info) {
            const p = info.event.extendedProps;
            info.el.title = [info.event.title, p.client, p.veterinarian ? 'Atiende: ' + p.veterinarian : null, p.status].filter(Boolean).join('\n');
        },
        select: function (info) {
            if (!createUrl) return;
            window.location = createUrl + '?start=' + encodeURIComponent(wall(info.start));
        },
        dateClick: function (info) {
            // En la vista mensual, un clic en el día abre ese día en vista diaria.
            if (info.view.type === 'dayGridMonth') calendar.changeView('timeGridDay', info.dateStr);
        },
        eventChange: function (info) {
            fetch(moveUrl.replace('__ID__', info.event.id), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ starts_at: wall(info.event.start), ends_at: wall(info.event.end) }),
            }).then(async function (response) {
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    info.revert();
                    notify(body.message || 'No se pudo reprogramar la cita.', false);
                } else {
                    notify(body.message || 'Cita reprogramada.', true);
                }
            }).catch(function () { info.revert(); notify('Sin conexión con el servidor.', false); });
        },
    });

    calendar.render();
    filters.addEventListener('change', () => calendar.refetchEvents());
});
</script>
@endpush
