<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Catálogo global de roles. Los slugs van en inglés; los nombres y
 * descripciones, en el lenguaje de una veterinaria.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrador',
                'slug' => 'super_admin',
                'description' => 'Operador de la plataforma Vexi',
            ],
            [
                'name' => 'Administrador',
                'slug' => 'admin',
                'description' => 'Administra toda la empresa: sucursales, personal y configuración',
            ],
            [
                'name' => 'Encargado de sucursal',
                'slug' => 'manager',
                'description' => 'Gestiona la operación diaria de una sucursal',
            ],
            [
                'name' => 'Cajero',
                'slug' => 'cashier',
                'description' => 'Cobra, abre y cierra caja',
            ],
            [
                'name' => 'Recepcionista',
                'slug' => 'receptionist',
                'description' => 'Agenda citas, registra clientes y mascotas, gestiona la sala de espera',
            ],
            [
                'name' => 'Veterinario',
                'slug' => 'veterinarian',
                'description' => 'Atiende consultas, diagnostica, receta y lleva el expediente clínico',
            ],
            [
                'name' => 'Auxiliar veterinario',
                'slug' => 'vet_assistant',
                'description' => 'Apoya en consultas, vacunas, hospitalización y cuidados',
            ],
            [
                'name' => 'Encargado de inventario',
                'slug' => 'inventory_manager',
                'description' => 'Gestiona farmacia, stock, lotes, vencimientos y compras',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
