<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Deworming;
use App\Models\Pet;
use App\Models\Product;
use App\Support\Staff;
use App\Support\Stock;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registro de desparasitaciones internas y externas (§5.4). */
class DewormingController extends Controller
{
    public function create()
    {
        $pet = request('pet_id') ? Pet::with(['client', 'species'])->find(request('pet_id')) : null;

        if (! $pet) {
            return view('admin.vaccinations.choose-pet', [
                'selectedClient' => request('client_id') ? Client::find(request('client_id')) : null,
                'action' => route('dewormings.create'),
                'title' => 'Registrar desparasitación',
            ]);
        }

        return view('admin.dewormings.create', $this->formData($pet) + [
            'deworming' => null,
            'consultationId' => request('consultation_id'),
        ]);
    }

    public function store()
    {
        $pet = Pet::findOrFail(request('pet_id'));
        $data = $this->validateDeworming($pet);

        DB::transaction(function () use ($pet, $data) {
            $deworming = Deworming::create($data + [
                'pet_id' => $pet->id,
                'created_by' => auth()->id(),
            ]);

            // Con producto del inventario, sale una unidad (ya se aplicó: admite faltante).
            if ($deworming->inventoryProduct && app(Tenancy::class)->company()?->planAllows('inventario')) {
                Stock::consume($deworming->inventoryProduct, Stock::defaultBranch(), 1, 'deworming', [
                    'source' => $deworming,
                    'allow_negative' => true,
                    'notes' => "{$pet->name} · desparasitación {$deworming->type_label}",
                ]);
            }
        });

        return redirect()->route('pets.show', ['pet' => $pet, 'tab' => 'health'])->with('success', 'Desparasitación registrada.');
    }

    public function edit(Deworming $deworming)
    {
        $pet = $deworming->pet->load(['client', 'species']);

        return view('admin.dewormings.edit', $this->formData($pet) + [
            'deworming' => $deworming,
            'consultationId' => $deworming->consultation_id,
        ]);
    }

    public function update(Deworming $deworming)
    {
        $deworming->update($this->validateDeworming($deworming->pet));

        return redirect()->route('pets.show', ['pet' => $deworming->pet_id, 'tab' => 'health'])->with('success', 'Desparasitación actualizada.');
    }

    public function destroy(Deworming $deworming)
    {
        $petId = $deworming->pet_id;

        DB::transaction(function () use ($deworming) {
            Stock::reverse($deworming, 'Registro de desparasitación eliminado');
            $deworming->delete();
        });

        return redirect()->route('pets.show', ['pet' => $petId, 'tab' => 'health'])->with('success', 'Registro de desparasitación eliminado.');
    }

    /** @return array<string, mixed> */
    protected function formData(Pet $pet): array
    {
        return [
            'pet' => $pet,
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
            // Productos ya usados por la veterinaria, como sugerencia.
            'productSuggestions' => Deworming::query()->distinct()->orderBy('product')->limit(200)->pluck('product'),
            'inventoryProducts' => app(Tenancy::class)->company()?->planAllows('inventario')
                ? Product::where('active', true)->orderBy('name')->get(['id', 'name', 'presentation'])
                : collect(),
        ];
    }

    /** @return array<string, mixed> */
    protected function validateDeworming(Pet $pet): array
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'type' => ['required', Rule::in(array_keys(Deworming::TYPES))],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'product' => 'required_without:product_id|nullable|string|max:160',
            'dose' => 'nullable|string|max:60',
            'weight' => 'nullable|numeric|min:0.01|max:9999',
            'applied_at' => 'required|date|before_or_equal:today',
            'next_due_at' => 'nullable|date|after:applied_at',
            'veterinarian_id' => ['nullable', 'integer'],
            'consultation_id' => [
                'nullable',
                Rule::exists('consultations', 'id')->where('company_id', $companyId)->where('pet_id', $pet->id),
            ],
            'notes' => 'nullable|string|max:2000',
        ], [
            'next_due_at.after' => 'La próxima aplicación debe ser posterior a esta.',
        ], [
            'type' => 'tipo',
            'product' => 'producto',
            'applied_at' => 'fecha de aplicación',
            'next_due_at' => 'próxima aplicación',
        ]);

        if (! empty($validated['veterinarian_id']) && ! Staff::belongsTo((int) $validated['veterinarian_id'], app(Tenancy::class)->company())) {
            throw ValidationException::withMessages(['veterinarian_id' => 'El profesional no pertenece a esta veterinaria.']);
        }

        // Elegido del inventario, el nombre del producto sale de ahí.
        if (! empty($validated['product_id']) && empty($validated['product'])) {
            $validated['product'] = Product::find($validated['product_id'])->label;
        }

        if (empty($validated['next_due_at']) && ! request()->boolean('no_next_due')) {
            $validated['next_due_at'] = Carbon::parse($validated['applied_at'])->addDays(Deworming::defaultIntervalDays($validated['type']));
        }

        return $validated;
    }
}
