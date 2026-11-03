<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Species;
use App\Models\Vaccine;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;

/** Catálogo de vacunas y su protocolo (§5.1). */
class VaccineController extends Controller
{
    public function index()
    {
        $vaccines = Vaccine::with('species')->withCount('vaccinations')->orderBy('name')->get();

        return view('admin.vaccines.index', compact('vaccines'));
    }

    public function create()
    {
        return view('admin.vaccines.create', ['species' => Species::orderBy('name')->get(), 'products' => $this->products()]);
    }

    public function store()
    {
        [$data, $speciesIds] = $this->validateVaccine();

        $vaccine = Vaccine::create($data + ['active' => request()->boolean('active', true)]);
        $vaccine->species()->sync($speciesIds);

        return redirect()->route('vaccines.index')->with('success', 'Vacuna agregada al catálogo.');
    }

    public function edit(Vaccine $vaccine)
    {
        return view('admin.vaccines.edit', [
            'vaccine' => $vaccine->load('species'),
            'species' => Species::orderBy('name')->get(),
            'products' => $this->products(),
        ]);
    }

    public function update(Vaccine $vaccine)
    {
        [$data, $speciesIds] = $this->validateVaccine();

        $vaccine->update($data + ['active' => request()->boolean('active', false)]);
        $vaccine->species()->sync($speciesIds);

        return redirect()->route('vaccines.index')->with('success', 'Vacuna actualizada.');
    }

    public function destroy(Vaccine $vaccine)
    {
        // Soft delete: las aplicaciones registradas conservan su vacuna.
        $vaccine->delete();

        return redirect()->route('vaccines.index')->with('success', 'Vacuna eliminada del catálogo.');
    }

    /** Productos del inventario para vincular (solo si el plan incluye inventario). */
    protected function products()
    {
        return app(Tenancy::class)->company()?->planAllows('inventario')
            ? Product::where('active', true)->orderBy('name')->get(['id', 'name', 'presentation'])
            : collect();
    }

    /** @return array{0: array<string, mixed>, 1: array<int, int>} */
    protected function validateVaccine(): array
    {
        $companyId = app(Tenancy::class)->id();

        $validated = request()->validate([
            'name' => 'required|string|max:120',
            'manufacturer' => 'nullable|string|max:120',
            'presentation' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:2000',
            'doses_in_series' => 'required|integer|min:1|max:10',
            'series_interval_days' => 'nullable|integer|min:1|max:365|required_unless:doses_in_series,1',
            'booster_interval_days' => 'nullable|integer|min:1|max:3650',
            'min_age_weeks' => 'nullable|integer|min:0|max:520',
            'active' => 'sometimes|boolean',
            'species' => 'array',
            'species.*' => [Rule::exists('species', 'id')->where('company_id', $companyId)],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
        ], [
            'series_interval_days.required_unless' => 'Con más de una dosis, indica los días entre dosis.',
        ], [
            'name' => 'nombre',
            'doses_in_series' => 'dosis de la serie',
            'series_interval_days' => 'días entre dosis',
            'booster_interval_days' => 'días hasta el refuerzo',
        ]);

        return [collect($validated)->except('species')->all(), $validated['species'] ?? []];
    }
}
