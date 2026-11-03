<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /** Paciente, propietario y empresa salen de la misma mascota. */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'pet_id' => Pet::factory(),
            'client_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->client_id,
            'company_id' => fn (array $attributes) => Pet::allCompanies()->find($attributes['pet_id'])->company_id,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'status' => 'pending',
        ];
    }
}
