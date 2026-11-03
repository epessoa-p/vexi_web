<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'slug' => 'basico',
                'description' => 'Consultorio veterinario de un solo local: agenda, consultas, vacunas, ventas y caja.',
                'price' => 49.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => 3,
                'max_branches' => 1,
                'max_products' => 50,
                'features' => ['agenda', 'consultas', 'vacunas', 'pos', 'caja'],
                'sort_order' => 1,
                'active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Clínica veterinaria con farmacia, ventas, laboratorio y recordatorios.',
                'price' => 99.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => 10,
                'max_branches' => 3,
                'max_products' => 500,
                'features' => [
                    'agenda', 'consultas', 'vacunas', 'laboratorio', 'inventario',
                    'compras', 'pos', 'caja', 'estadisticas', 'recordatorios',
                ],
                'sort_order' => 2,
                'active' => true,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Hospital veterinario, todo incluido y sin límites: hospitalización y servicios adicionales.',
                'price' => 189.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => null,
                'max_branches' => null,
                'max_products' => null,
                'features' => array_keys(Plan::MODULES),
                'sort_order' => 3,
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
