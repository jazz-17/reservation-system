<?php

namespace Database\Factories;

use App\Models\Enums\ReservationStatus;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 20))->setTime(10, 0)->utc();
        $endsAt = (clone $startsAt)->addHour();
        $min = 2018;
        $max = max($min, (int) now()->year);

        return [
            'user_id' => User::factory(),
            'status' => ReservationStatus::Pending,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'professional_school_id' => ProfessionalSchool::factory(),
            'base_year' => fake()->numberBetween($min, $max),
        ];
    }
}
