<?php

namespace Database\Factories;

use App\Models\Enums\ReservationStatus;
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

        return [
            'user_id' => User::factory(),
            'status' => ReservationStatus::Pending,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'professional_school' => fake()->randomElement(['E.P. Sistemas', 'E.P. Industrial', 'E.P. Software']),
            'base' => fake()->randomElement(['B22', 'B23', 'B24']),
        ];
    }
}
