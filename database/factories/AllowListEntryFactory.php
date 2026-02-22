<?php

namespace Database\Factories;

use App\Models\ProfessionalSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AllowListEntry>
 */
class AllowListEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $min = 2018;
        $max = max($min, (int) now()->year);

        return [
            'email' => fake()->unique()->safeEmail(),
            'professional_school_id' => ProfessionalSchool::factory(),
            'base_year' => fake()->numberBetween($min, $max),
        ];
    }
}
