<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Deworming;
use App\Models\Vaccination;
use App\Models\Pet;
use App\Models\PetWeight;
use App\Models\Species;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Mascotas / pacientes y su ficha (antecedentes + historial de peso).
 * El route model binding pasa por el CompanyScope: una mascota de otra
 * veterinaria da 404.
 */
class PetController extends Controller
{
    public function index()
    {
        $pets = Pet::with(['client', 'species', 'breed'])
            ->search(request('q'))
            ->when(request('species_id'), fn ($q, $id) => $q->where('species_id', $id))
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pets.index', [
            'pets' => $pets,
            'species' => Species::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.pets.create', $this->formData() + [
            'selectedClient' => request('client_id') ? Client::find(request('client_id')) : null,
        ]);
    }

    public function store()
    {
        $validated = $this->validatePet();

        try {
            $pet = DB::transaction(function () use ($validated) {
                $pet = Pet::create([
                    ...collect($validated)->except('photo')->all(),
                    'sterilized' => request()->boolean('sterilized'),
                    'photo' => $this->storePhoto(request()->file('photo')),
                ]);

                // El peso inicial abre el historial.
                if ($pet->weight !== null) {
                    $pet->weights()->create([
                        'weight' => $pet->weight,
                        'recorded_at' => now()->toDateString(),
                        'notes' => 'Peso al registrar',
                        'recorded_by' => auth()->id(),
                    ]);
                }

                return $pet;
            });

            return redirect()->route('pets.show', $pet)->with('success', 'Mascota registrada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al crear mascota', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la mascota.']);
        }
    }

    public function show(Pet $pet)
    {
        $pet->load(['client', 'species', 'breed', 'weights.recorder']);

        // Próximas citas activas y las últimas cinco, para el resumen de la ficha.
        $upcomingAppointments = $pet->appointments()->with(['service', 'veterinarian'])
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->where('ends_at', '>=', now()->startOfDay())
            ->reorder('starts_at')
            ->get();
        $pastAppointments = $pet->appointments()->with(['service', 'veterinarian'])
            ->whereNotIn('id', $upcomingAppointments->pluck('id'))
            ->limit(5)
            ->get();

        // Expediente clínico: consultas con sus diagnósticos, recetas y procedimientos.
        $consultations = $pet->consultations()
            ->with(['diagnoses', 'prescriptionItems', 'procedures', 'veterinarian', 'followups'])
            ->get();

        // Sanidad: historial y lo pendiente (atrasado primero) de vacunas y desparasitaciones.
        $vaccinations = $pet->vaccinations()->with(['vaccine', 'veterinarian'])->get();
        $dewormings = $pet->dewormings()->with('veterinarian')->get();
        $pendingHealth = Vaccination::pendingDue()->where('pet_id', $pet->id)->with('vaccine')->get()
            ->map(fn ($v) => ['title' => $v->vaccine?->name, 'due' => $v->next_due_at])
            ->concat(Deworming::pendingDue()->where('pet_id', $pet->id)->get()
                ->map(fn ($d) => ['title' => "Desparasitación {$d->type_label}", 'due' => $d->next_due_at]))
            ->sortBy('due')
            ->values();

        return view('admin.pets.show', compact(
            'pet', 'upcomingAppointments', 'pastAppointments', 'consultations', 'vaccinations', 'dewormings', 'pendingHealth'
        ));
    }

    public function edit(Pet $pet)
    {
        return view('admin.pets.edit', $this->formData() + [
            'pet' => $pet,
            'selectedClient' => $pet->client,
        ]);
    }

    public function update(Pet $pet)
    {
        $validated = $this->validatePet($pet);

        try {
            DB::transaction(function () use ($pet, $validated) {
                $data = [
                    ...collect($validated)->except(['photo', 'weight'])->all(),
                    'sterilized' => request()->boolean('sterilized'),
                ];

                if (request()->boolean('remove_photo')) {
                    $this->deletePhoto($pet->photo);
                    $data['photo'] = null;
                }

                if ($file = request()->file('photo')) {
                    $this->deletePhoto($pet->photo);
                    $data['photo'] = $this->storePhoto($file);
                }

                $pet->update($data);

                // Un peso distinto al vigente es una medición nueva: va al
                // historial con fecha de hoy en vez de sobrescribir el anterior.
                $weight = $validated['weight'] ?? null;
                if ($weight !== null && (float) $weight !== (float) $pet->weight) {
                    $pet->weights()->create([
                        'weight' => $weight,
                        'recorded_at' => now()->toDateString(),
                        'recorded_by' => auth()->id(),
                    ]);
                    $pet->syncCurrentWeight();
                }
            });

            return redirect()->route('pets.show', $pet)->with('success', 'Mascota actualizada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar mascota', ['pet_id' => $pet->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar la mascota.']);
        }
    }

    public function destroy(Pet $pet)
    {
        try {
            // Soft delete: la foto se conserva junto con la historia clínica.
            $pet->delete();
            return redirect()->route('pets.index')->with('success', 'Mascota eliminada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar mascota', ['pet_id' => $pet->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible eliminar la mascota.']);
        }
    }

    /** Ficha del paciente: antecedentes médicos y alertas clínicas. */
    public function updateHistory(Pet $pet)
    {
        $validated = request()->validate([
            'medical_history' => 'nullable|string|max:5000',
            'allergies' => 'nullable|string|max:2000',
            'chronic_conditions' => 'nullable|string|max:2000',
            'previous_surgeries' => 'nullable|string|max:2000',
            'usual_medications' => 'nullable|string|max:2000',
            'clinical_alerts' => 'nullable|string|max:1000',
        ]);

        $pet->update($validated);

        return redirect()->route('pets.show', ['pet' => $pet, 'tab' => 'history'])
            ->with('success', 'Antecedentes actualizados.');
    }

    public function storeWeight(Pet $pet)
    {
        $validated = request()->validate([
            'weight' => 'required|numeric|min:0.01|max:9999',
            'recorded_at' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:255',
        ], [], ['weight' => 'peso', 'recorded_at' => 'fecha']);

        DB::transaction(function () use ($pet, $validated) {
            $pet->weights()->create([...$validated, 'recorded_by' => auth()->id()]);
            $pet->syncCurrentWeight();
        });

        return redirect()->route('pets.show', ['pet' => $pet, 'tab' => 'weight'])
            ->with('success', 'Peso registrado.');
    }

    public function destroyWeight(Pet $pet, PetWeight $weight)
    {
        abort_unless($weight->pet_id === $pet->id, 404);

        DB::transaction(function () use ($pet, $weight) {
            $weight->delete();
            $pet->syncCurrentWeight();
        });

        return redirect()->route('pets.show', ['pet' => $pet, 'tab' => 'weight'])
            ->with('success', 'Registro de peso eliminado.');
    }

    /** @return array<string, mixed> */
    protected function formData(): array
    {
        return [
            'species' => Species::with(['breeds' => fn ($q) => $q->where('active', true)])
                ->where('active', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    /** @return array<string, mixed> */
    protected function validatePet(?Pet $pet = null): array
    {
        $companyId = app(Tenancy::class)->id();

        return request()->validate([
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
            ],
            'species_id' => ['required', Rule::exists('species', 'id')->where('company_id', $companyId)],
            'breed_id' => [
                'nullable',
                Rule::exists('breeds', 'id')->where('company_id', $companyId)->where('species_id', request('species_id')),
            ],
            'name' => 'required|string|max:80',
            'sex' => ['required', Rule::in(array_keys(Pet::SEXES))],
            'sterilized' => 'sometimes|boolean',
            'birth_date' => 'nullable|date|before_or_equal:today',
            'weight' => 'nullable|numeric|min:0.01|max:9999',
            'color' => 'nullable|string|max:80',
            'size' => ['nullable', Rule::in(array_keys(Pet::SIZES))],
            'microchip' => [
                'nullable', 'string', 'max:30',
                Rule::unique('pets', 'microchip')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($pet?->id),
            ],
            'identification' => 'nullable|string|max:30',
            'status' => ['required', Rule::in(array_keys(Pet::STATUSES))],
            'notes' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:4096',
        ], [
            'breed_id.exists' => 'La raza no corresponde a la especie elegida.',
            'microchip.unique' => 'Ya existe una mascota con ese microchip.',
        ], [
            'client_id' => 'propietario',
            'species_id' => 'especie',
            'breed_id' => 'raza',
            'name' => 'nombre',
            'sex' => 'sexo',
            'birth_date' => 'fecha de nacimiento',
            'weight' => 'peso',
            'photo' => 'fotografía',
        ]);
    }

    protected function storePhoto(?UploadedFile $file): ?string
    {
        return $file?->store('pets/' . app(Tenancy::class)->id(), 'public');
    }

    protected function deletePhoto(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
