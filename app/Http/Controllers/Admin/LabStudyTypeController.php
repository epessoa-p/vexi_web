<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabStudyType;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Catálogo de estudios y sus valores de referencia (§6.2). */
class LabStudyTypeController extends Controller
{
    public function index()
    {
        return view('admin.lab-studies.index', [
            'studyTypes' => LabStudyType::withCount(['parameters', 'requests'])->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.lab-studies.create', ['studyType' => null, 'parameters' => []]);
    }

    public function store()
    {
        [$data, $parameters] = $this->validateStudyType();

        DB::transaction(function () use ($data, $parameters) {
            $studyType = LabStudyType::create($data + ['active' => request()->boolean('active', true)]);
            $this->syncParameters($studyType, $parameters);
        });

        return redirect()->route('lab-studies.index')->with('success', 'Estudio agregado al catálogo.');
    }

    public function edit(LabStudyType $studyType)
    {
        return view('admin.lab-studies.edit', [
            'studyType' => $studyType,
            'parameters' => $studyType->parameters->map->only(['name', 'unit', 'reference_min', 'reference_max', 'reference_text'])->all(),
        ]);
    }

    public function update(LabStudyType $studyType)
    {
        [$data, $parameters] = $this->validateStudyType($studyType);

        // Los informes ya emitidos conservan su copia de la referencia.
        DB::transaction(function () use ($studyType, $data, $parameters) {
            $studyType->update($data + ['active' => request()->boolean('active')]);
            $this->syncParameters($studyType, $parameters);
        });

        return redirect()->route('lab-studies.index')->with('success', 'Estudio actualizado.');
    }

    public function destroy(LabStudyType $studyType)
    {
        if ($studyType->requests()->exists()) {
            return back()->withErrors(['error' => 'El estudio tiene solicitudes. Desactívalo en lugar de eliminarlo.']);
        }

        $studyType->delete();

        return redirect()->route('lab-studies.index')->with('success', 'Estudio eliminado.');
    }

    protected function syncParameters(LabStudyType $studyType, array $parameters): void
    {
        $studyType->parameters()->reorder()->delete();

        foreach (array_values($parameters) as $sort => $parameter) {
            $studyType->parameters()->create($parameter + ['sort' => $sort]);
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    protected function validateStudyType(?LabStudyType $studyType = null): array
    {
        request()->merge(['parameters' => collect(request('parameters', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))->values()->all()]);

        $validated = request()->validate([
            'name' => ['required', 'string', 'max:120',
                Rule::unique('lab_study_types', 'name')->where('company_id', app(Tenancy::class)->id())->ignore($studyType?->id)],
            'category' => ['required', Rule::in(array_keys(LabStudyType::CATEGORIES))],
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0|max:9999999',
            'active' => 'sometimes|boolean',
            'parameters' => 'array',
            'parameters.*.name' => 'required|string|max:80',
            'parameters.*.unit' => 'nullable|string|max:30',
            'parameters.*.reference_min' => 'nullable|numeric',
            'parameters.*.reference_max' => 'nullable|numeric',
            'parameters.*.reference_text' => 'nullable|string|max:120',
        ], ['name.unique' => 'Ese estudio ya existe.'], ['name' => 'nombre', 'category' => 'categoría']);

        $validated['price'] = (float) ($validated['price'] ?? 0);

        return [collect($validated)->except(['parameters', 'active'])->all(), $validated['parameters'] ?? []];
    }
}
