<?php

namespace Database\Factories;

use App\Models\Hospitalization;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hospitalization>
 */
class HospitalizationFactory extends Factory
{
    protected $model = Hospitalization::class;

    /** La empresa sale de la mascota. */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'company_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->company_id,
            'status' => 'active',
            'condition' => 'observation',
            'admitted_at' => now()->subHours(2),
            'reason' => 'Observación',
            'daily_rate' => 100,
        ];
    }
}
