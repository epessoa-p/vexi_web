<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Qué permisos otorga cada rol.
 *
 * Va aparte del alta de usuarios a propósito: cuando esta asignación vivía
 * dentro de SuperAdminSeeder, referenciaba slugs inexistentes y tres roles
 * quedaron en silencio sin ningún permiso. Por eso al final se avisa de
 * cualquier slug que no exista en la base de datos.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'admin' => [
                'companies.view',
                'users.view', 'users.create', 'users.edit', 'users.delete',
                'cargos.view', 'cargos.create', 'cargos.edit', 'cargos.delete',
                'personal.view', 'personal.create', 'personal.edit', 'personal.delete',
                'branches.view', 'branches.create', 'branches.edit', 'branches.delete',
                'cajas.view', 'cajas.create', 'cajas.edit', 'cajas.delete',
                'subscriptions.view',
            ],
            'manager' => [
                'users.view',
                'cargos.view',
                'personal.view', 'personal.create', 'personal.edit',
                'branches.view',
                'cajas.view', 'cajas.create', 'cajas.edit',
            ],
            'cashier' => [
                'branches.view',
                'personal.view',
                'cajas.view',
            ],
            'receptionist' => [
                'branches.view',
                'personal.view',
                'cajas.view',
            ],
            'veterinarian' => [
                'branches.view',
                'personal.view',
            ],
            'vet_assistant' => [
                'personal.view',
            ],
            'inventory_manager' => [
                'branches.view',
                'personal.view',
            ],
        ];

        $missing = [];

        // El operador de la plataforma tiene todos los permisos.
        if ($superAdmin = Role::where('slug', 'super_admin')->first()) {
            $superAdmin->permissions()->sync(Permission::pluck('id'));
        }

        foreach ($matrix as $roleSlug => $slugs) {
            $role = Role::where('slug', $roleSlug)->first();

            if (! $role) {
                $missing[] = "rol «{$roleSlug}»";
                continue;
            }

            $ids = Permission::whereIn('slug', $slugs)->pluck('id', 'slug');

            foreach (array_diff($slugs, $ids->keys()->all()) as $slug) {
                $missing[] = "{$roleSlug} → {$slug}";
            }

            $role->permissions()->sync($ids->values());
        }

        if ($missing) {
            $this->command?->warn(
                'RolePermissionSeeder: estos permisos no existen y se ignoraron: '
                . implode(', ', $missing)
            );
        }
    }
}
