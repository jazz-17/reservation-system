<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProfessionalSchool>
 */
class ProfessionalSchoolFactory extends Factory
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
            'faculty_id' => Faculty::factory(),
            'name' => fake()->unique()->words(asText: true),
            'base_year_min' => $min,
            'base_year_max' => $max,
            'active' => true,
        ];
    }
}
