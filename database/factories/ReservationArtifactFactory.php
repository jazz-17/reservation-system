<?php

namespace Database\Factories;

use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationArtifact>
 */
class ReservationArtifactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'kind' => ReservationArtifactKind::Pdf,
            'status' => ReservationArtifactStatus::Pending,
            'attempts' => 0,
            'payload' => [],
        ];
    }
}
