<?php

use App\Jobs\GenerateReservationPdf;
use App\Jobs\SendReservationEmail;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationArtifact;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

test('email artifacts are not enqueued when email notifications are disabled', function () {
    Queue::fake();

    Setting::query()->create([
        'key' => 'email_notifications_enabled',
        'value' => false,
        'updated_by' => null,
    ]);

    Setting::query()->create([
        'key' => 'notify_admin_emails',
        'value' => ['to' => ['admin.notify@example.edu'], 'cc' => [], 'bcc' => []],
        'updated_by' => null,
    ]);

    $admin = User::factory()->admin()->create();
    $student = User::factory()->create();

    $startsAtUtc = CarbonImmutable::now('America/Lima')->addDay()->setTime(15, 0)->setTimezone('UTC');
    $reservation = Reservation::factory()->create([
        'user_id' => $student->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAtUtc,
        'ends_at' => $startsAtUtc->addHour(),
        'professional_school_id' => $student->professional_school_id,
        'base_year' => $student->base_year,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.requests.approve', $reservation), ['reason' => null])
        ->assertRedirect();

    expect(ReservationArtifact::query()
        ->where('reservation_id', $reservation->id)
        ->where('kind', ReservationArtifactKind::Pdf)
        ->exists())->toBeTrue();

    expect(ReservationArtifact::query()
        ->where('reservation_id', $reservation->id)
        ->whereIn('kind', [ReservationArtifactKind::EmailAdmin, ReservationArtifactKind::EmailStudent])
        ->exists())->toBeFalse();

    Queue::assertPushed(GenerateReservationPdf::class);
    Queue::assertNotPushed(SendReservationEmail::class);
});
