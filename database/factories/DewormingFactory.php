<?php

namespace Database\Factories;

use App\Models\Deworming;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deworming>
 */
class DewormingFactory extends Factory
{
    protected $model = Deworming::class;

    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'company_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->company_id,
            'type' => 'internal',
            'product' => 'Antiparasitario',
            'applied_at' => now()->subMonth()->toDateString(),
            'next_due_at' => now()->addMonths(2)->toDateString(),
        ];
    }
}
