<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blackout>
 */
class BlackoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 20))->startOfHour();
        $endsAt = (clone $startsAt)->addHours(fake()->numberBetween(1, 4));

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => fake()->sentence(),
        ];
    }
}
