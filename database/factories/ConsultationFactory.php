<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    /** Propietario y empresa salen de la mascota. */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'client_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->client_id,
            'company_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->company_id,
            'status' => 'in_progress',
            'started_at' => now(),
            'reason' => 'Control',
        ];
    }

    public function finished(): static
    {
        return $this->state(fn () => ['status' => 'finished', 'finished_at' => now()]);
    }
}
