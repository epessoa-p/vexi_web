<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Vaccine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaccine>
 */
class VaccineFactory extends Factory
{
    protected $model = Vaccine::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Vacuna '.fake()->unique()->numerify('####'),
            'doses_in_series' => 3,
            'series_interval_days' => 21,
            'booster_interval_days' => 365,
            'active' => true,
        ];
    }
}
