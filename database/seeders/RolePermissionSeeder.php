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
                'clients.view', 'clients.create', 'clients.edit', 'clients.delete',
                'pets.view', 'pets.create', 'pets.edit', 'pets.delete',
                'species.view', 'species.create', 'species.edit', 'species.delete',
                'services.view', 'services.create', 'services.edit', 'services.delete',
                'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                'consultations.view', 'consultations.create', 'consultations.edit', 'consultations.delete',
                'vaccinations.view', 'vaccinations.create', 'vaccinations.edit', 'vaccinations.delete',
                'vaccines.view', 'vaccines.create', 'vaccines.edit', 'vaccines.delete',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'inventory.view', 'inventory.create', 'inventory.edit',
                'sales.view', 'sales.create', 'sales.edit',
                'cash.view', 'cash.create',
                'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',
                'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                'purchases.view', 'purchases.create', 'purchases.edit',
            ],
            'manager' => [
                'users.view',
                'cargos.view',
                'personal.view', 'personal.create', 'personal.edit',
                'branches.view',
                'cajas.view', 'cajas.create', 'cajas.edit',
                'clients.view', 'clients.create', 'clients.edit',
                'pets.view', 'pets.create', 'pets.edit',
                'species.view', 'species.create', 'species.edit',
                'services.view', 'services.create', 'services.edit',
                'appointments.view', 'appointments.create', 'appointments.edit', 'appointments.delete',
                'consultations.view',
                'vaccinations.view',
                'vaccines.view',
                'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.create', 'inventory.edit',
                'sales.view', 'sales.create', 'sales.edit',
                'cash.view', 'cash.create',
                'expenses.view', 'expenses.create', 'expenses.edit',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'purchases.view', 'purchases.create', 'purchases.edit',
            ],
            'cashier' => [
                'branches.view',
                'personal.view',
                'cajas.view',
                'clients.view', 'clients.create',
                'pets.view',
                'services.view',
                'appointments.view',
                'products.view',
                'sales.view', 'sales.create', 'sales.edit',
                'cash.view', 'cash.create',
                'expenses.view', 'expenses.create',
                'suppliers.view',
                'purchases.view',
            ],
            'receptionist' => [
                'branches.view',
                'personal.view',
                'cajas.view',
                'clients.view', 'clients.create', 'clients.edit',
                'pets.view', 'pets.create', 'pets.edit',
                'services.view',
                'appointments.view', 'appointments.create', 'appointments.edit',
                'consultations.view',
                'vaccinations.view',
                'products.view',
                'sales.view', 'sales.create',
                'cash.view',
            ],
            'veterinarian' => [
                'branches.view',
                'personal.view',
                'clients.view', 'clients.create', 'clients.edit',
                'pets.view', 'pets.create', 'pets.edit',
                'species.view', 'species.create', 'species.edit',
                'services.view',
                'appointments.view', 'appointments.create', 'appointments.edit',
                'consultations.view', 'consultations.create', 'consultations.edit', 'consultations.delete',
                'vaccinations.view', 'vaccinations.create', 'vaccinations.edit', 'vaccinations.delete',
                'vaccines.view', 'vaccines.create', 'vaccines.edit',
                'products.view',
                'inventory.view',
            ],
            'vet_assistant' => [
                'personal.view',
                'clients.view',
                'pets.view', 'pets.edit',
                'services.view',
                'appointments.view', 'appointments.edit',
                'consultations.view', 'consultations.edit',
                'vaccinations.view', 'vaccinations.create', 'vaccinations.edit',
                'vaccines.view',
                'products.view',
                'inventory.view',
            ],
            'inventory_manager' => [
                'branches.view',
                'personal.view',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'inventory.view', 'inventory.create', 'inventory.edit',
                'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
                'purchases.view', 'purchases.create', 'purchases.edit',
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
