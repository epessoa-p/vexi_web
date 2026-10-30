<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CargoController extends Controller
{
    public function index()
    {
        // El CompanyScope ya filtra por la empresa activa.
        $query = Cargo::with(['company', 'role'])->latest();

        if (auth()->user()->is_super_admin && request('company_id')) {
            $query->forCompany((int) request('company_id'));
        }

        $cargos = $query->paginate(15);

        return view('admin.cargos.index', compact('cargos'));
    }

    public function create()
    {
        $authUser = auth()->user();

        return view('admin.cargos.create', [
            'roles' => Role::orderBy('name')->get(),
            'permissions' => Permission::all()->groupBy('module'),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id')
            : (int) $authUser->getCurrentCompany()?->id;

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'role_mode' => ['required', 'in:existing,new'],
                'role_id' => ['required_if:role_mode,existing', 'nullable', 'exists:roles,id'],
                'new_role_name' => ['required_if:role_mode,new', 'nullable', 'string', 'max:255'],
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('cargos', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
                'description' => ['nullable', 'string', 'max:2000'],
                'active' => ['nullable', 'boolean'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['exists:permissions,id'],
            ]);

            if (!$companyId) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            DB::transaction(function () use ($validated, $companyId, $request) {
                if ($validated['role_mode'] === 'new') {
                    $slug = Str::of($validated['new_role_name'])->lower()->ascii()->replace(' ', '_')->toString();
                    $baseSlug = $slug ?: 'role';
                    $counter = 1;
                    while (Role::where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '_' . $counter;
                        $counter++;
                    }

                    $role = Role::create([
                        'name' => trim($validated['new_role_name']),
                        'slug' => $slug,
                    ]);
                } else {
                    $role = Role::findOrFail($validated['role_id']);
                }

                // Sync permissions to the role
                if ($request->has('permissions')) {
                    $role->permissions()->sync($validated['permissions'] ?? []);
                }

                Cargo::create([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', true),
                ]);
            });

            return redirect()->route('cargos.index')->with('success', 'Cargo creado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear cargo', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el cargo.']);
        }
    }

    public function edit(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);
        $authUser = auth()->user();

        $cargo->load('role.permissions');

        return view('admin.cargos.edit', [
            'cargo' => $cargo,
            'roles' => Role::orderBy('name')->get(),
            'permissions' => Permission::all()->groupBy('module'),
            'companies' => $authUser->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$authUser->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Request $request, Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        $authUser = auth()->user();
        $companyId = $authUser->is_super_admin
            ? (int) $request->input('company_id', $cargo->company_id)
            : (int) $cargo->company_id;

        try {
            $validated = $request->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'role_mode' => ['required', 'in:existing,new'],
                'role_id' => ['required_if:role_mode,existing', 'nullable', 'exists:roles,id'],
                'new_role_name' => ['required_if:role_mode,new', 'nullable', 'string', 'max:255'],
                'name' => [
                    'required',
                    'string',
                    'max:150',
                    Rule::unique('cargos', 'name')
                        ->ignore($cargo->id)
                        ->where(fn ($q) => $q->where('company_id', $companyId)),
                ],
                'description' => ['nullable', 'string', 'max:2000'],
                'active' => ['nullable', 'boolean'],
                'permissions' => ['nullable', 'array'],
                'permissions.*' => ['exists:permissions,id'],
            ]);

            DB::transaction(function () use ($validated, $cargo, $companyId, $request) {
                if ($validated['role_mode'] === 'new') {
                    $slug = Str::of($validated['new_role_name'])->lower()->ascii()->replace(' ', '_')->toString();
                    $baseSlug = $slug ?: 'role';
                    $counter = 1;
                    while (Role::where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '_' . $counter;
                        $counter++;
                    }

                    $role = Role::create([
                        'name' => trim($validated['new_role_name']),
                        'slug' => $slug,
                    ]);
                } else {
                    $role = Role::findOrFail($validated['role_id']);
                }

                // Sync permissions to the role
                if ($request->has('permissions')) {
                    $role->permissions()->sync($validated['permissions'] ?? []);
                }

                $cargo->update([
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'name' => trim($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'active' => $request->boolean('active', false),
                ]);
            });

            return redirect()->route('cargos.index')->with('success', 'Cargo actualizado exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar cargo', ['cargo_id' => $cargo->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el cargo.']);
        }
    }

    public function destroy(Cargo $cargo)
    {
        $this->authorizeCargo($cargo);

        if ($cargo->personals()->exists()) {
            return back()->withErrors(['error' => 'No puedes eliminar un cargo con personal asignado.']);
        }

        $cargo->delete();

        return redirect()->route('cargos.index')->with('success', 'Cargo eliminado exitosamente.');
    }

    /**
     * API: return permissions for a given role (AJAX).
     */
    public function rolePermissions(Role $role)
    {
        return response()->json($role->permissions->pluck('id'));
    }

    protected function authorizeCargo(Cargo $cargo): void
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $cargo->company_id !== $authUser->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
