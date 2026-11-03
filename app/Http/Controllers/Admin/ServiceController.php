<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/** Catálogo de servicios de la veterinaria (§11.1–11.2). */
class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::withCount('appointments')
            ->when(request('category'), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store()
    {
        Service::create($this->validateService() + ['active' => request()->boolean('active', true)]);

        return redirect()->route('services.index')->with('success', 'Servicio creado exitosamente.');
    }

    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    public function update(Service $service)
    {
        $service->update($this->validateService() + ['active' => request()->boolean('active', false)]);

        return redirect()->route('services.index')->with('success', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Service $service)
    {
        try {
            // Soft delete: las citas pasadas conservan su servicio.
            $service->delete();
            return redirect()->route('services.index')->with('success', 'Servicio eliminado exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar servicio', ['service_id' => $service->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible eliminar el servicio.']);
        }
    }

    /** @return array<string, mixed> */
    protected function validateService(): array
    {
        $validated = request()->validate([
            'name' => 'required|string|max:120',
            'category' => ['required', Rule::in(array_keys(Service::CATEGORIES))],
            'price' => 'required|numeric|min:0|max:9999999',
            'duration_minutes' => 'required|integer|min:5|max:1440',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => 'nullable|string|max:2000',
            'active' => 'sometimes|boolean',
        ], [], [
            'name' => 'nombre',
            'category' => 'categoría',
            'price' => 'precio',
            'duration_minutes' => 'duración',
        ]);

        // Sin color propio usa el de su categoría.
        if (! request()->boolean('custom_color')) {
            $validated['color'] = null;
        }

        return $validated;
    }
}
