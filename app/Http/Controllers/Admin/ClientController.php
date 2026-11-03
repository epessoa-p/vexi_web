<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Pet;
use App\Models\Sale;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Clientes / propietarios. El CompanyScope filtra por la empresa activa, y el
 * route model binding pasa por él: un cliente de otra veterinaria da 404.
 */
class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::withCount('pets')
            ->search(request('q'))
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    /** Autocompletado de propietarios (formularios de mascota, y luego agenda y POS). */
    public function search()
    {
        $clients = Client::where('active', true)
            ->search(request('q'))
            ->orderBy('first_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'document_type', 'document_number', 'phone']);

        return response()->json($clients->map(fn (Client $client) => [
            'id' => $client->id,
            'name' => $client->full_name,
            'detail' => collect([
                $client->document_number ? "{$client->document_type} {$client->document_number}" : null,
                $client->phone,
            ])->filter()->implode(' · '),
        ]));
    }

    /** Mascotas activas de un propietario (JSON), para elegir paciente en citas y consultas. */
    public function pets(Client $client)
    {
        return response()->json($client->pets()->where('status', 'active')->with('species')->get()
            ->map(fn (Pet $pet) => [
                'id' => $pet->id,
                'name' => $pet->name,
                'detail' => trim($pet->species?->name . ($pet->age_label ? " · {$pet->age_label}" : '')),
                'warning' => $pet->hasClinicalWarnings(),
            ]));
    }

    public function create()
    {
        return view('admin.clients.create');
    }

    public function store()
    {
        $validated = $this->validateClient();

        try {
            $client = Client::create([
                ...$validated,
                'active' => request()->boolean('active', true),
            ]);

            // "Guardar y registrar mascota": el flujo habitual en recepción.
            if (request('after') === 'pet') {
                return redirect()->route('pets.create', ['client_id' => $client->id])
                    ->with('success', 'Cliente registrado. Ahora registra su mascota.');
            }

            return redirect()->route('clients.show', $client)->with('success', 'Cliente registrado exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al crear cliente', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el cliente.']);
        }
    }

    public function show(Client $client)
    {
        $client->load(['pets.species', 'pets.breed']);

        // Cuenta del cliente: compras, pagos y saldo pendiente (si la veterinaria vende).
        $sales = app(Tenancy::class)->company()?->planAllows('pos')
            ? Sale::where('client_id', $client->id)->latest('id')->limit(10)->get()
            : null;
        $balance = $sales !== null ? (float) Sale::where('client_id', $client->id)->receivable()->sum('balance') : 0;

        return view('admin.clients.show', compact('client', 'sales', 'balance'));
    }

    public function edit(Client $client)
    {
        return view('admin.clients.edit', compact('client'));
    }

    public function update(Client $client)
    {
        $validated = $this->validateClient($client);

        try {
            $client->update([
                ...$validated,
                'active' => request()->boolean('active', false),
            ]);

            return redirect()->route('clients.show', $client)->with('success', 'Cliente actualizado exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar cliente', ['client_id' => $client->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el cliente.']);
        }
    }

    public function destroy(Client $client)
    {
        // Las mascotas guardan historia clínica: no se borran en cascada.
        if ($client->pets()->exists()) {
            return back()->withErrors([
                'error' => 'El cliente tiene mascotas registradas. Desactívalo en lugar de eliminarlo.',
            ]);
        }

        try {
            $client->delete();
            return redirect()->route('clients.index')->with('success', 'Cliente eliminado exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar cliente', ['client_id' => $client->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible eliminar el cliente.']);
        }
    }

    /** @return array<string, mixed> */
    protected function validateClient(?Client $client = null): array
    {
        $companyId = app(Tenancy::class)->id();

        return request()->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'document_type' => ['nullable', Rule::in(array_keys(Client::DOCUMENT_TYPES))],
            'document_number' => [
                'nullable', 'string', 'max:30',
                Rule::unique('clients', 'document_number')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($client?->id),
            ],
            'phone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'active' => 'sometimes|boolean',
        ], [
            'document_number.unique' => 'Ya existe un cliente con ese documento.',
        ], [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'document_number' => 'número de documento',
        ]);
    }
}
