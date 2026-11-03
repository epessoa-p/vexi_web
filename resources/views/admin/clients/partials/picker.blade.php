{{--
    Buscador de propietario con autocompletado.
    Parámetros: $selectedClient (?Client), $prefix (ids únicos si hay varios en la página).
    Emite en el document los eventos `{prefix}:selected` (detail: {id, name}) y `{prefix}:cleared`.
--}}
@php $prefix = $prefix ?? 'client'; @endphp
<input type="hidden" name="client_id" id="{{ $prefix }}-id" value="{{ $selectedClient?->id }}">

<div id="{{ $prefix }}-selected" class="d-flex align-items-center justify-content-between border rounded p-2 {{ $selectedClient ? '' : 'd-none' }}">
    <div>
        <i class="bi bi-person-check text-success"></i>
        <span class="fw-semibold" id="{{ $prefix }}-selected-name">{{ $selectedClient?->full_name }}</span>
    </div>
    <button type="button" class="btn btn-sm btn-link text-decoration-none" id="{{ $prefix }}-change">Cambiar</button>
</div>

<div id="{{ $prefix }}-picker" class="position-relative {{ $selectedClient ? 'd-none' : '' }}">
    <input type="search" id="{{ $prefix }}-search" class="form-control @error('client_id') is-invalid @enderror" placeholder="Busca por nombre, documento o teléfono…" autocomplete="off">
    <div id="{{ $prefix }}-results" class="list-group position-absolute w-100 shadow-sm d-none" style="z-index: 1050; max-height: 280px; overflow-y: auto;"></div>
    @if(auth()->user()->hasPermissionInCompany('clients.create', $tenantCompany))
        <div class="form-text">¿No está registrado? <a href="{{ route('clients.create') }}">Registrar cliente nuevo</a></div>
    @endif
</div>

<script>
(function () {
    const prefix = @json($prefix);
    const searchUrl = @json(route('clients.search'));
    const $ = (suffix) => document.getElementById(prefix + '-' + suffix);
    const input = $('search'), results = $('results'), hidden = $('id'), picker = $('picker'), selected = $('selected');
    let timer = null;

    function choose(client) {
        hidden.value = client.id;
        $('selected-name').textContent = client.name;
        selected.classList.remove('d-none');
        picker.classList.add('d-none');
        results.classList.add('d-none');
        document.dispatchEvent(new CustomEvent(prefix + ':selected', { detail: client }));
    }

    $('change').addEventListener('click', function () {
        hidden.value = '';
        selected.classList.add('d-none');
        picker.classList.remove('d-none');
        input.value = '';
        input.focus();
        document.dispatchEvent(new CustomEvent(prefix + ':cleared'));
    });

    input.addEventListener('input', function () {
        clearTimeout(timer);
        const term = input.value.trim();
        if (term.length < 2) { results.classList.add('d-none'); return; }
        timer = setTimeout(async function () {
            const response = await fetch(searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } });
            const clients = response.ok ? await response.json() : [];
            results.innerHTML = '';
            if (!clients.length) {
                results.innerHTML = '<div class="list-group-item text-muted small">Sin resultados</div>';
            }
            for (const client of clients) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = '<div class="fw-semibold"></div><small class="text-muted"></small>';
                item.querySelector('div').textContent = client.name;
                item.querySelector('small').textContent = client.detail;
                item.addEventListener('click', () => choose(client));
                results.appendChild(item);
            }
            results.classList.remove('d-none');
        }, 250);
    });

    document.addEventListener('click', function (event) {
        if (!picker.contains(event.target)) results.classList.add('d-none');
    });
})();
</script>
