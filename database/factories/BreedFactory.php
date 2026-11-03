<?php

namespace Database\Factories;

use App\Models\Breed;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Breed>
 */
class BreedFactory extends Factory
{
    protected $model = Breed::class;

    public function definition(): array
    {
        return [
            'species_id' => Species::factory(),
            'company_id' => fn (array $attributes) => Species::allCompanies()->find($attributes['species_id'])->company_id,
            'name' => 'Raza '.fake()->unique()->numerify('####'),
            'active' => true,
        ];
    }
}
