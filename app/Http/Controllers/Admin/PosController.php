<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\SaleItem;
use App\Models\PaymentMethod;
use App\Models\Pet;
use App\Models\Product;
use App\Models\Service;
use App\Support\Billables;
use App\Support\Cash;
use App\Support\Sales;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Punto de venta (§9.1). */
class PosController extends Controller
{
    public function create()
    {
        $pet = request('pet_id') ? Pet::with('client')->find(request('pet_id')) : null;
        $client = $pet?->client ?? (request('client_id') ? Client::find(request('client_id')) : null);

        return view('admin.pos.create', [
            'selectedClient' => $client,
            'selectedPetId' => $pet?->id,
            'services' => Service::where('active', true)->orderBy('name')->get(['id', 'name', 'price', 'category']),
            'paymentMethods' => PaymentMethod::where('active', true)->orderBy('sort')->get(),
            'sessions' => Cash::enabled() ? Cash::openSessions() : collect(),
            'cashEnabled' => Cash::enabled(),
            'withInventory' => (bool) app(Tenancy::class)->company()?->planAllows('inventario'),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store()
    {
        $companyId = app(Tenancy::class)->id();
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        $validated = request()->validate([
            'client_id' => ['nullable', $exists('clients')->whereNull('deleted_at')],
            'pet_id' => ['nullable', $exists('pets')->where('client_id', request('client_id'))],
            'branch_id' => ['nullable', $exists('branches')],
            'cash_session_id' => ['nullable', 'integer'],
            'discount' => 'nullable|numeric|min:0|max:9999999',
            'due_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.type' => ['required', Rule::in(array_keys(SaleItem::TYPES))],
            'items.*.product_id' => ['nullable', 'required_if:items.*.type,product', $exists('products')->whereNull('deleted_at')],
            'items.*.service_id' => ['nullable', $exists('services')],
            'items.*.consultation_id' => ['nullable', $exists('consultations')->where('status', 'finished')],
            'items.*.lab_request_id' => ['nullable', $exists('lab_requests')->whereNot('status', 'cancelled')],
            'items.*.hospitalization_id' => ['nullable', $exists('hospitalizations')->whereIn('status', ['discharged', 'deceased'])],
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01|max:99999',
            'items.*.unit_price' => 'required|numeric|min:0|max:9999999',
            'items.*.discount' => 'nullable|numeric|min:0|max:9999999',
            'payments' => 'array',
            'payments.*.payment_method_id' => ['required', $exists('payment_methods')->where('active', true)],
            'payments.*.amount' => 'required|numeric|min:0|max:9999999',
            'payments.*.reference' => 'nullable|string|max:80',
        ], [
            'items.required' => 'Agrega al menos un ítem a la venta.',
            'pet_id.exists' => 'La mascota no pertenece al cliente elegido.',
        ]);

        $session = Cash::sessionFor($validated['cash_session_id'] ?? null);
        $methods = PaymentMethod::whereIn('id', collect($validated['payments'] ?? [])->pluck('payment_method_id'))->get()->keyBy('id');

        $sale = Sales::create([
            'client_id' => $validated['client_id'] ?? null,
            'pet_id' => $validated['pet_id'] ?? null,
            'branch' => $session?->caja?->branch
                ?? (! empty($validated['branch_id']) ? Branch::find($validated['branch_id']) : null),
            'session' => $session,
            'discount' => (float) ($validated['discount'] ?? 0),
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'items' => collect($validated['items'])->map(fn (array $item) => $item + [
                // El servidor decide qué descuenta stock: un producto de mostrador
                // sí; uno que viene de una consulta o internación ya salió del inventario.
                'affects_stock' => $item['type'] === 'product' && empty($item['consultation_id']) && empty($item['hospitalization_id']),
            ])->values()->all(),
            'payments' => collect($validated['payments'] ?? [])->map(fn (array $p) => [
                'method' => $methods[$p['payment_method_id']],
                'amount' => (float) $p['amount'],
                'reference' => $p['reference'] ?? null,
            ])->all(),
        ]);

        return redirect()->route('sales.show', ['sale' => $sale, 'printed' => 1])
            ->with('success', "Venta {$sale->code} registrada.");
    }

    /** Lo atendido a la mascota que todavía no se cobró. */
    public function billable(Pet $pet)
    {
        return response()->json(Billables::forPet($pet));
    }
}
