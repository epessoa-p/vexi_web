<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\Vaccination;
use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaccination>
 */
class VaccinationFactory extends Factory
{
    protected $model = Vaccination::class;

    /** La vacuna se crea en la misma empresa que la mascota. */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'company_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->company_id,
            'vaccine_id' => fn (array $attributes) => Vaccine::factory()->create(['company_id' => $attributes['company_id']])->id,
            'applied_at' => now()->subMonth()->toDateString(),
            'dose_number' => 1,
            'next_due_at' => now()->addMonth()->toDateString(),
        ];
    }
}
