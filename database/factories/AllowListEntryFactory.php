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
        $year = fake()->numberBetween($min, $max);
        $yy = str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT);
        $studentCode = $yy.fake()->numerify('######');

        return [
            'email' => fake()->unique()->safeEmail(),
            'professional_school_id' => ProfessionalSchool::factory(),
            'student_code' => $studentCode,
            'base_year' => $year,
        ];
    }
}
