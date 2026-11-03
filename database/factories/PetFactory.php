<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Pet;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    /** Propietario y especie se crean en la misma empresa que la mascota. */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'company_id' => fn (array $attributes) => Client::allCompanies()->find($attributes['client_id'])->company_id,
            'species_id' => fn (array $attributes) => Species::factory()->create(['company_id' => $attributes['company_id']])->id,
            'name' => fake()->firstName(),
            'sex' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'weight' => fake()->randomFloat(2, 1, 40),
            'status' => 'active',
        ];
    }
}
