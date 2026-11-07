<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LabStudyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabStudyType>
 */
class LabStudyTypeFactory extends Factory
{
    protected $model = LabStudyType::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Estudio '.fake()->unique()->numerify('####'),
            'category' => 'hematology',
            'active' => true,
        ];
    }
}
