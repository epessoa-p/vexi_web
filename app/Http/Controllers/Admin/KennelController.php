<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Hospitalization;
use App\Models\Kennel;
use App\Support\Tenancy;
use Illuminate\Validation\Rule;

/** Espacios de internación, editables en línea. */
class KennelController extends Controller
{
    public function index()
    {
        return view('admin.kennels.index', [
            'kennels' => Kennel::with('branch')->withCount('hospitalizations')->orderBy('name')->get(),
            'occupied' => Hospitalization::where('status', 'active')->whereNotNull('kennel_id')->pluck('kennel_id'),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store()
    {
        Kennel::create($this->validateKennel() + ['active' => true]);

        return back()->with('success', 'Espacio agregado.');
    }

    public function update(Kennel $kennel)
    {
        $kennel->update($this->validateKennel($kennel) + ['active' => request()->boolean('active')]);

        return back()->with('success', 'Espacio actualizado.');
    }

    public function destroy(Kennel $kennel)
    {
        if ($kennel->hospitalizations()->exists()) {
            return back()->withErrors(['error' => 'El espacio tiene historial de internaciones. Desactívalo en lugar de eliminarlo.']);
        }

        $kennel->delete();

        return back()->with('success', 'Espacio eliminado.');
    }

    /** @return array<string, mixed> */
    protected function validateKennel(?Kennel $kennel = null): array
    {
        $companyId = app(Tenancy::class)->id();

        return request()->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('kennels', 'name')->where('company_id', $companyId)->ignore($kennel?->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'notes' => 'nullable|string|max:255',
        ], ['name.unique' => 'Ya existe un espacio con ese nombre.'], ['name' => 'nombre']);
    }
}
