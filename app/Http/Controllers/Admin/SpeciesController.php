<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Breed;
use App\Models\Species;
use App\Support\ClinicalCatalog;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;

/** Configuración clínica: especies y sus razas, por veterinaria. */
class SpeciesController extends Controller
{
    public function index()
    {
        $species = Species::withCount(['breeds', 'pets'])->orderBy('name')->get();

        return view('admin.species.index', compact('species'));
    }

    public function store()
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueSpeciesName()],
        ], ['name.unique' => 'Esa especie ya existe.'], ['name' => 'nombre']);

        $species = Species::create($validated + ['active' => true]);

        return redirect()->route('species.show', $species)->with('success', 'Especie creada. Agrega sus razas.');
    }

    public function show(Species $species)
    {
        $species->load('breeds')->loadCount('pets');
        $breedUsage = Breed::where('species_id', $species->id)->withCount('pets')->pluck('pets_count', 'id');

        return view('admin.species.show', compact('species', 'breedUsage'));
    }

    public function update(Species $species)
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueSpeciesName($species)],
            'active' => 'sometimes|boolean',
        ], ['name.unique' => 'Esa especie ya existe.'], ['name' => 'nombre']);

        $species->update([
            'name' => $validated['name'],
            'active' => request()->boolean('active'),
        ]);

        return redirect()->route('species.show', $species)->with('success', 'Especie actualizada.');
    }

    public function destroy(Species $species)
    {
        if ($species->pets()->withTrashed()->exists()) {
            return back()->withErrors(['error' => 'Hay mascotas de esta especie. Desactívala en lugar de eliminarla.']);
        }

        $species->delete();

        return redirect()->route('species.index')->with('success', 'Especie eliminada.');
    }

    public function storeBreed(Species $species)
    {
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('breeds', 'name')->where('species_id', $species->id)],
        ], ['name.unique' => 'Esa raza ya existe en esta especie.'], ['name' => 'raza']);

        $species->breeds()->create($validated + ['active' => true]);

        return redirect()->route('species.show', $species)->with('success', 'Raza agregada.');
    }

    public function updateBreed(Species $species, Breed $breed)
    {
        abort_unless($breed->species_id === $species->id, 404);

        $validated = request()->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('breeds', 'name')->where('species_id', $species->id)->ignore($breed->id),
            ],
            'active' => 'sometimes|boolean',
        ], ['name.unique' => 'Esa raza ya existe en esta especie.'], ['name' => 'raza']);

        $breed->update([
            'name' => $validated['name'],
            'active' => request()->boolean('active'),
        ]);

        return redirect()->route('species.show', $species)->with('success', 'Raza actualizada.');
    }

    public function destroyBreed(Species $species, Breed $breed)
    {
        abort_unless($breed->species_id === $species->id, 404);

        if ($breed->pets()->withTrashed()->exists()) {
            return back()->withErrors(['error' => 'Hay mascotas de esta raza. Desactívala en lugar de eliminarla.']);
        }

        $breed->delete();

        return redirect()->route('species.show', $species)->with('success', 'Raza eliminada.');
    }

    /** Vuelve a agregar las especies y razas base que falten (no toca las editadas). */
    public function restoreDefaults()
    {
        ClinicalCatalog::seedFor(app(Tenancy::class)->id());

        return redirect()->route('species.index')->with('success', 'Catálogo base restaurado.');
    }

    protected function uniqueSpeciesName(?Species $species = null)
    {
        return Rule::unique('species', 'name')
            ->where('company_id', app(Tenancy::class)->id())
            ->ignore($species?->id);
    }
}
