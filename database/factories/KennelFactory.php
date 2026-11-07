<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Kennel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kennel>
 */
class KennelFactory extends Factory
{
    protected $model = Kennel::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Espacio '.fake()->unique()->numerify('####'),
            'active' => true,
        ];
    }
}
