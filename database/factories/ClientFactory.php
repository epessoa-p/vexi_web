<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => 'CI',
            'document_number' => fake()->unique()->numerify('#######'),
            'phone' => fake()->numerify('+591 7#######'),
            'email' => fake()->unique()->safeEmail(),
            'active' => true,
        ];
    }
}
