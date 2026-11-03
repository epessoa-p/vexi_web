<?php

namespace Database\Seeders;

use App\Support\Tenancy;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Sin esto, cualquier modelo tenant que se siembre quedaría filtrado
        // por una empresa activa que aquí no existe (y en tests, vacío).
        app(Tenancy::class)->runUnrestricted(function () {
            // El orden importa: los permisos antes de asignarlos, los planes
            // antes de las suscripciones, las empresas antes de sus usuarios.
            $this->call([
                RoleSeeder::class,
                PermissionSeeder::class,
                RolePermissionSeeder::class,
                PlanSeeder::class,
                CompanySeeder::class,
                ClinicalCatalogSeeder::class,
                SubscriptionSeeder::class,
                SuperAdminSeeder::class,
                DemoPatientsSeeder::class,
            ]);
        });
    }
}
