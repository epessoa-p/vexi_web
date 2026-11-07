<?php

namespace Database\Factories;

use App\Models\LabRequest;
use App\Models\LabStudyType;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabRequest>
 */
class LabRequestFactory extends Factory
{
    protected $model = LabRequest::class;

    /** Empresa y estudio salen de la mascota. */
    public function definition(): array
    {
        return [
            'pet_id' => Pet::factory(),
            'company_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->company_id,
            'lab_study_type_id' => fn (array $attributes) => LabStudyType::factory()->create(['company_id' => $attributes['company_id']])->id,
            'requested_at' => now(),
            'status' => 'requested',
        ];
    }
}
