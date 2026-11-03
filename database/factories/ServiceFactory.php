<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Servicio '.fake()->unique()->numerify('####'),
            'category' => 'consulta',
            'price' => fake()->randomFloat(2, 50, 300),
            'duration_minutes' => 30,
            'active' => true,
        ];
    }
}
