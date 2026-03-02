<?php

use App\Actions\Reservations\ReservationRulesService;
use App\Actions\Reservations\ReservationService;
use App\Actions\Settings\SettingsService;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('createPending re-throws non-exclusion query exceptions', function () {
    $user = User::factory()->create();

    // Point to a non-existent school — FK violation on INSERT (SQLSTATE 23503), not 23P01
    $user->professional_school_id = 999999;

    $startsAt = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');
    $endsAt = $startsAt->addHour();

    $service = app(ReservationService::class);
    $service->createPending($user, $startsAt, $endsAt);
})->throws(QueryException::class);

test('approve re-throws non-exclusion query exceptions', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $startsAt = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');

    $reservation = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    // Delete admin so decided_by FK constraint fails (SQLSTATE 23503), not 23P01
    DB::table('users')->where('id', $admin->id)->delete();

    $service = app(ReservationService::class);
    $service->approve($admin, $reservation);
})->throws(QueryException::class);

test('approve converts exclusion constraint violation to validation error', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $startsAt = CarbonImmutable::now('America/Lima')->addDay()->setTime(10, 0)->setTimezone('UTC');

    Reservation::factory()->create([
        'status' => ReservationStatus::Approved,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
    ]);

    $pending = Reservation::factory()->create([
        'user_id' => $user->id,
        'status' => ReservationStatus::Pending,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
        'professional_school_id' => $user->professional_school_id,
        'base_year' => $user->base_year,
    ]);

    // Bypass application-level conflict check so the DB exclusion constraint fires
    $mockRules = Mockery::mock(ReservationRulesService::class);
    $mockRules->shouldReceive('validateForApproval')->once();

    $service = new ReservationService(app(SettingsService::class), $mockRules);

    try {
        $service->approve($admin, $pending);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('starts_at');
        expect($e->errors()['starts_at'][0])->toBe('Horario no disponible.');
    }
});
