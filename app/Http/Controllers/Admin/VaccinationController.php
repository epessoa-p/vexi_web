<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\ProductBatch;
use App\Models\Vaccination;
use App\Models\Vaccine;
use App\Support\Staff;
use App\Support\Stock;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Registro de vacunas aplicadas (§5.2). */
class VaccinationController extends Controller
{
    public function create()
    {
        $pet = request('pet_id') ? Pet::with(['client', 'species'])->find(request('pet_id')) : null;

        // Sin paciente, primero se elige propietario y mascota.
        if (! $pet) {
            return view('admin.vaccinations.choose-pet', [
                'selectedClient' => request('client_id') ? Client::find(request('client_id')) : null,
                'action' => route('vaccinations.create'),
                'title' => 'Aplicar vacuna',
            ]);
        }

        return view('admin.vaccinations.create', $this->formData($pet) + [
            'vaccination' => null,
            'consultationId' => request('consultation_id'),
        ]);
    }

    public function store()
    {
        $pet = Pet::findOrFail(request('pet_id'));
        $data = $this->validateVaccination($pet);

        DB::transaction(function () use ($pet, $data) {
            $vaccination = Vaccination::create($data + [
                'pet_id' => $pet->id,
                'created_by' => auth()->id(),
            ]);
            $this->discountStock($vaccination);
        });

        return redirect()->route('pets.show', ['pet' => $pet, 'tab' => 'health'])->with('success', 'Vacuna registrada.');
    }

    public function edit(Vaccination $vaccination)
    {
        $pet = $vaccination->pet->load(['client', 'species']);

        return view('admin.vaccinations.edit', $this->formData($pet, $vaccination) + [
            'vaccination' => $vaccination,
            'consultationId' => $vaccination->consultation_id,
        ]);
    }

    public function update(Vaccination $vaccination)
    {
        $vaccination->update($this->validateVaccination($vaccination->pet, $vaccination));

        return redirect()->route('pets.show', ['pet' => $vaccination->pet_id, 'tab' => 'health'])->with('success', 'Vacuna actualizada.');
    }

    public function destroy(Vaccination $vaccination)
    {
        $petId = $vaccination->pet_id;

        DB::transaction(function () use ($vaccination) {
            // La dosis vuelve al stock con un movimiento de anulación.
            Stock::reverse($vaccination, 'Registro de vacuna eliminado');
            $vaccination->delete();
        });

        return redirect()->route('pets.show', ['pet' => $petId, 'tab' => 'health'])->with('success', 'Registro de vacuna eliminado.');
    }

    /**
     * Si la vacuna está vinculada a un producto del inventario, descuenta una
     * dosis: del lote anotado si existe en la sucursal, si no por FEFO. Sin
     * lote anotado, la aplicación toma el del frasco que salió del stock.
     */
    protected function discountStock(Vaccination $vaccination): void
    {
        $product = $vaccination->vaccine?->product;

        if (! $product || ! app(Tenancy::class)->company()?->planAllows('inventario')) {
            return;
        }

        $branch = $vaccination->consultation?->branch ?? Stock::defaultBranch();
        $batch = $vaccination->lot
            ? ProductBatch::where('product_id', $product->id)->where('branch_id', $branch->id)->where('lot', $vaccination->lot)->first()
            : null;

        $movements = Stock::consume($product, $branch, 1, 'vaccination', [
            'batch' => $batch,
            'source' => $vaccination,
            'allow_negative' => true,
            'notes' => "{$vaccination->pet?->name} · dosis {$vaccination->dose_number}",
        ]);

        $used = $movements->first()?->batch;
        if (! $vaccination->lot && $used) {
            $vaccination->update(['lot' => $used->lot, 'lot_expires_at' => $used->expires_at]);
        }
    }

    /** @return array<string, mixed> */
    protected function formData(Pet $pet, ?Vaccination $editing = null): array
    {
        $vaccines = Vaccine::with('species')->where('active', true)->orderBy('name')->get()
            ->filter(fn (Vaccine $v) => $v->appliesTo($pet->species_id) || $v->id === $editing?->vaccine_id)
            ->values();

        // Última dosis registrada de cada vacuna, para proponer la siguiente.
        $lastDoses = $pet->vaccinations()
            ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
            ->get()
            ->groupBy('vaccine_id')
            ->map(fn ($items) => $items->first()->dose_number);

        return [
            'pet' => $pet,
            'vaccines' => $vaccines,
            'lastDoses' => $lastDoses,
            'professionals' => Staff::professionals(app(Tenancy::class)->company()),
        ];
    }

    /** @return array<string, mixed> */
    protected function validateVaccination(Pet $pet, ?Vaccination $vaccination = null): array
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'vaccine_id' => ['required', Rule::exists('vaccines', 'id')->where('company_id', $companyId)],
            'consultation_id' => [
                'nullable',
                Rule::exists('consultations', 'id')->where('company_id', $companyId)->where('pet_id', $pet->id),
            ],
            'applied_at' => 'required|date|before_or_equal:today',
            'dose_number' => 'required|integer|min:1|max:50',
            'lot' => 'nullable|string|max:60',
            'lot_expires_at' => 'nullable|date|after_or_equal:applied_at',
            'veterinarian_id' => ['nullable', 'integer'],
            'next_due_at' => 'nullable|date|after:applied_at',
            'notes' => 'nullable|string|max:2000',
        ], [
            'lot_expires_at.after_or_equal' => 'El lote estaba vencido en la fecha de aplicación.',
            'next_due_at.after' => 'La próxima dosis debe ser posterior a la aplicación.',
        ], [
            'vaccine_id' => 'vacuna',
            'applied_at' => 'fecha de aplicación',
            'dose_number' => 'número de dosis',
            'lot_expires_at' => 'vencimiento del lote',
            'next_due_at' => 'próxima dosis',
        ]);

        $vaccine = Vaccine::with('species')->find($validated['vaccine_id']);

        if (! $vaccine->appliesTo($pet->species_id) && $vaccine->id !== $vaccination?->vaccine_id) {
            throw ValidationException::withMessages(['vaccine_id' => "La vacuna {$vaccine->name} no corresponde a la especie de {$pet->name}."]);
        }

        if (! empty($validated['veterinarian_id']) && ! Staff::belongsTo((int) $validated['veterinarian_id'], app(Tenancy::class)->company())) {
            throw ValidationException::withMessages(['veterinarian_id' => 'El profesional no pertenece a esta veterinaria.']);
        }

        // Sin fecha manual, la próxima dosis sale del protocolo (salvo que se pida no programarla).
        if (empty($validated['next_due_at']) && ! request()->boolean('no_next_due')) {
            $validated['next_due_at'] = $vaccine->nextDueDate(Carbon::parse($validated['applied_at']), (int) $validated['dose_number']);
        }

        return $validated;
    }
}
