<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

/**
 * Dos veterinarias a propósito: con una sola no se puede comprobar a mano que el
 * aislamiento por empresa funciona de verdad.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'tax_id' => '1023456789',
                'name' => 'Veterinaria Demo',
                'tax_id_label' => 'NIT',
                'country' => 'BO',
                'currency' => 'BOB',
                'timezone' => 'America/La_Paz',
                'address' => 'Av. Ballivián 1234, Santa Cruz',
                'phone' => '+591 3 123-4567',
                'email' => 'hola@veterinariademo.test',
                'description' => 'Clínica veterinaria de demostración con el plan Premium.',
                'active' => true,
            ],
            [
                'tax_id' => '9876543210',
                'name' => 'Veterinaria Prueba',
                'tax_id_label' => 'NIT',
                'country' => 'BO',
                'currency' => 'BOB',
                'timezone' => 'America/La_Paz',
                'address' => 'Calle Comercio 456, La Paz',
                'phone' => '+591 2 765-4321',
                'email' => 'hola@veterinariaprueba.test',
                'description' => 'Segunda empresa, para verificar el aislamiento entre tenants.',
                'active' => true,
            ],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(['tax_id' => $company['tax_id']], $company);
        }
    }
}
