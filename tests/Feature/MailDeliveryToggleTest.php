<?php

use App\Actions\Settings\SettingsService;
use App\Jobs\SendReservationEmail;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\ReservationArtifact;

test('mail delivery can be suppressed and reservation email artifacts are marked skipped', function () {
    config()->set('mail.delivery_enabled', false);

    $artifact = ReservationArtifact::factory()->create([
        'kind' => ReservationArtifactKind::EmailStudent,
        'status' => ReservationArtifactStatus::Pending,
        'payload' => [
            'event' => 'approved',
            'to' => ['student@example.com'],
            'cc' => [],
            'bcc' => [],
        ],
    ]);

    $job = new SendReservationEmail($artifact->id);
    $job->handle(app(SettingsService::class));

    $artifact->refresh();
    expect($artifact->status)->toBe(ReservationArtifactStatus::Skipped);
});
