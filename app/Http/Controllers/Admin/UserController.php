<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->is_super_admin) {
            $users = User::paginate(15);
        } else {
            $company = $user->getCurrentCompany();
            $users = $company->users()->paginate(15);
        }

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $user = auth()->user();
        $roles = Role::all();
        $companies = $user->is_super_admin ? Company::all() : $user->companies()->get();

        return view('admin.users.create', compact('roles', 'companies'));
    }

    public function store(StoreUserRequest $request)
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'phone' => $request->phone,
                'is_super_admin' => $request->boolean('is_super_admin', false),
            ]);

            if ($request->has('companies') && $request->companies) {
                foreach ($request->companies as $companyId => $roleId) {
                    if ($roleId) {
                        $user->companies()->attach($companyId, ['role_id' => $roleId]);
                    }
                }
            }

            return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente');
        } catch (\Throwable $exception) {
            Log::error('Error al crear usuario', [
                'user' => $request->only('name', 'email'),
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible crear el usuario.']);
        }
    }

    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $authUser = auth()->user();
        $companies = $authUser->is_super_admin ? Company::all() : $authUser->companies()->get();

        return view('admin.users.edit', compact('user', 'roles', 'companies'));
    }

    public function update(StoreUserRequest $request, User $user)
    {
        try {
            $data = $request->validated();

            if (!auth()->user()->is_super_admin) {
                unset($data['is_super_admin']);
            }

            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);

            return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar usuario', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el usuario.']);
        }
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->user()->id) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado exitosamente');
    }

    /**
     * Asignar rol a usuario en una empresa
     */
    public function assignRole(User $user, Company $company, Role $role)
    {
        $authUser = auth()->user();

        if (!$authUser->is_super_admin && $authUser->getCurrentCompany()->id !== $company->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => $role->id]]);

        return back()->with('success', 'Rol asignado exitosamente');
    }
}
