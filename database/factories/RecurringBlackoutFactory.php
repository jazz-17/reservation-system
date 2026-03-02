<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RecurringBlackout>
 */
class RecurringBlackoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weekday = fake()->numberBetween(0, 6);

        $startHour = fake()->numberBetween(8, 18);
        $durationHours = fake()->numberBetween(1, 4);
        $endHour = min(23, $startHour + $durationHours);

        $startsOn = fake()->boolean(30)
            ? now()->addDays(fake()->numberBetween(1, 60))->toDateString()
            : null;

        $endsOn = $startsOn !== null && fake()->boolean(60)
            ? now()->addDays(fake()->numberBetween(61, 120))->toDateString()
            : null;

        return [
            'weekday' => $weekday,
            'starts_time' => sprintf('%02d:00', $startHour),
            'ends_time' => sprintf('%02d:00', $endHour),
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'reason' => fake()->sentence(),
        ];
    }
}
