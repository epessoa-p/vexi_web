<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Especie '.fake()->unique()->numerify('####'),
            'active' => true,
        ];
    }
}
