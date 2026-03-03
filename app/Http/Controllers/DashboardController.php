<?php

namespace App\Http\Controllers;

use App\Actions\Settings\SettingsService;
use App\Models\Blackout;
use App\Models\Enums\ReservationStatus;
use App\Models\RecurringBlackout;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const WEEKDAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __invoke(Request $request, SettingsService $settings): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $timezone = (string) config('app.timezone', 'America/Lima');
        $nowLocal = CarbonImmutable::now($timezone);
        $nowUtc = $nowLocal->setTimezone('UTC');

        /** @var array<string, array{open: string, close: string}> $openingHours */
        $openingHours = $settings->get('opening_hours');

        if ($user->hasPermissionTo('admin.panel.access')) {
            return $this->adminDashboard($nowLocal, $nowUtc, $timezone, $openingHours);
        }

        return Inertia::render('Dashboard', [
            'upcoming_reservations' => $this->upcomingReservations($user, $nowUtc),
            'active_count' => Reservation::query()
                ->where('user_id', $user->id)
                ->blocking()
                ->count(),
            'max_active' => $settings->getInt('max_active_reservations_per_user'),
            'weekly_quota_used' => $this->weeklyQuotaUsed($user, $nowLocal),
            'weekly_quota_max' => $settings->getInt('weekly_quota_per_school_base'),
            'recent_activity' => $this->recentActivity($user),
            'upcoming_blackouts' => $this->upcomingBlackouts($nowUtc, $nowLocal, $timezone),
            'todays_opening_hours' => $this->todaysOpeningHours($nowLocal, $openingHours),
        ]);
    }

    /**
     * @param  array<string, array{open: string, close: string}>  $openingHours
     */
    private function adminDashboard(
        CarbonImmutable $nowLocal,
        CarbonImmutable $nowUtc,
        string $timezone,
        array $openingHours,
    ): Response {
        return Inertia::render('Dashboard', [
            'is_admin' => true,
            'pending_count' => Reservation::query()
                ->where('status', ReservationStatus::Pending)
                ->count(),
            'todays_approved_count' => $this->todaysApprovedCount($nowLocal),
            'todays_opening_hours' => $this->todaysOpeningHours($nowLocal, $openingHours),
            'todays_reservations' => $this->todaysReservations($nowLocal),
            'recent_decisions' => $this->recentDecisions(),
            'week_at_a_glance' => $this->weekAtAGlance($nowLocal),
            'upcoming_blackouts' => $this->upcomingBlackouts($nowUtc, $nowLocal, $timezone),
        ]);
    }

    private function todaysApprovedCount(CarbonImmutable $nowLocal): int
    {
        $todayStartUtc = $nowLocal->startOfDay()->setTimezone('UTC');
        $todayEndUtc = $nowLocal->endOfDay()->setTimezone('UTC');

        return Reservation::query()
            ->approved()
            ->overlapping($todayStartUtc, $todayEndUtc)
            ->count();
    }

    /**
     * @return array<int, array{id: int, starts_at: string, ends_at: string, user_name: string, school_name: string|null}>
     */
    private function todaysReservations(CarbonImmutable $nowLocal): array
    {
        $todayStartUtc = $nowLocal->startOfDay()->setTimezone('UTC');
        $todayEndUtc = $nowLocal->endOfDay()->setTimezone('UTC');

        return Reservation::query()
            ->approved()
            ->overlapping($todayStartUtc, $todayEndUtc)
            ->with(['user:id,first_name,last_name', 'professionalSchool:id,name'])
            ->orderBy('starts_at')
            ->limit(6)
            ->get(['id', 'starts_at', 'ends_at', 'user_id', 'professional_school_id'])
            ->map(fn (Reservation $r): array => [
                'id' => $r->id,
                'starts_at' => $r->starts_at->toISOString(),
                'ends_at' => $r->ends_at->toISOString(),
                'user_name' => trim("{$r->user->first_name} {$r->user->last_name}"),
                'school_name' => $r->professionalSchool?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, status: string, decided_at: string, user_name: string, decider_name: string|null}>
     */
    private function recentDecisions(): array
    {
        return Reservation::query()
            ->whereNotNull('decided_at')
            ->with(['user:id,first_name,last_name', 'decider:id,first_name,last_name'])
            ->latest('decided_at')
            ->limit(5)
            ->get(['id', 'status', 'decided_at', 'user_id', 'decided_by'])
            ->map(fn (Reservation $r): array => [
                'id' => $r->id,
                'status' => $r->status->value,
                'decided_at' => $r->decided_at->toISOString(),
                'user_name' => trim("{$r->user->first_name} {$r->user->last_name}"),
                'decider_name' => $r->decider
                    ? trim("{$r->decider->first_name} {$r->decider->last_name}")
                    : null,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, date: string, count: int}>
     */
    private function weekAtAGlance(CarbonImmutable $nowLocal): array
    {
        $weekStart = $nowLocal->startOfWeek(CarbonInterface::MONDAY);
        $dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $dayLocal = $weekStart->addDays($i);
            $dayStartUtc = $dayLocal->startOfDay()->setTimezone('UTC');
            $dayEndUtc = $dayLocal->endOfDay()->setTimezone('UTC');

            $days[] = [
                'label' => $dayLabels[$i],
                'date' => $dayLocal->toDateString(),
                'count' => Reservation::query()
                    ->approved()
                    ->overlapping($dayStartUtc, $dayEndUtc)
                    ->count(),
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingReservations(User $user, CarbonImmutable $nowUtc): array
    {
        return Reservation::query()
            ->where('user_id', $user->id)
            ->blocking()
            ->where('ends_at', '>', $nowUtc)
            ->orderBy('starts_at')
            ->limit(3)
            ->get(['id', 'status', 'starts_at', 'ends_at', 'created_at'])
            ->toArray();
    }

    private function weeklyQuotaUsed(User $user, CarbonImmutable $nowLocal): int
    {
        if ($user->professional_school_id === null || $user->base_year === null) {
            return 0;
        }

        $weekStartLocal = $nowLocal->startOfWeek(CarbonInterface::MONDAY);
        $weekEndLocal = $weekStartLocal->addWeek();

        $weekStartUtc = $weekStartLocal->setTimezone('UTC');
        $weekEndUtc = $weekEndLocal->setTimezone('UTC');

        return Reservation::query()
            ->blocking()
            ->where('professional_school_id', $user->professional_school_id)
            ->where('base_year', $user->base_year)
            ->where('starts_at', '>=', $weekStartUtc)
            ->where('starts_at', '<', $weekEndUtc)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(User $user): array
    {
        return Reservation::query()
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'status', 'starts_at', 'ends_at', 'created_at', 'updated_at'])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingBlackouts(
        CarbonImmutable $nowUtc,
        CarbonImmutable $nowLocal,
        string $timezone,
    ): array {
        $horizonUtc = $nowUtc->addDays(7);

        // One-time blackouts
        $oneTime = Blackout::query()
            ->where('ends_at', '>', $nowUtc)
            ->where('starts_at', '<', $horizonUtc)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'reason'])
            ->map(fn (Blackout $b): array => [
                'starts_at' => $b->starts_at->toISOString(),
                'ends_at' => $b->ends_at->toISOString(),
                'reason' => $b->reason,
            ])
            ->all();

        // Recurring blackouts materialized for the next 7 days
        $recurring = $this->materializeRecurringBlackouts($nowLocal, $nowLocal->addDays(7), $timezone);

        $all = array_merge($oneTime, $recurring);

        usort($all, fn (array $a, array $b): int => strcmp($a['starts_at'], $b['starts_at']));

        return $all;
    }

    /**
     * @return array<int, array{starts_at: string, ends_at: string, reason: string|null}>
     */
    private function materializeRecurringBlackouts(
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        string $timezone,
    ): array {
        $rangeStart = $startLocal->startOfDay();
        $rangeEnd = $endLocal->subSecond()->startOfDay();
        $rangeStartDate = $rangeStart->toDateString();
        $rangeEndDate = $rangeEnd->toDateString();

        $weekdays = [];
        for ($cursor = $rangeStart; $cursor->lessThanOrEqualTo($rangeEnd); $cursor = $cursor->addDay()) {
            $weekdays[] = $cursor->dayOfWeek;
        }
        $weekdays = array_values(array_unique($weekdays));

        $rules = RecurringBlackout::query()
            ->whereIn('weekday', $weekdays)
            ->where(function ($query) use ($rangeEndDate): void {
                $query->whereNull('starts_on')->orWhere('starts_on', '<=', $rangeEndDate);
            })
            ->where(function ($query) use ($rangeStartDate): void {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', $rangeStartDate);
            })
            ->orderBy('weekday')
            ->orderBy('starts_time')
            ->get();

        $rulesByWeekday = $rules->groupBy('weekday');
        $events = [];

        for ($cursor = $rangeStart; $cursor->lessThanOrEqualTo($rangeEnd); $cursor = $cursor->addDay()) {
            $dayRules = $rulesByWeekday->get($cursor->dayOfWeek);

            if ($dayRules === null) {
                continue;
            }

            $dateString = $cursor->toDateString();

            foreach ($dayRules as $rule) {
                if ($rule->starts_on !== null && $dateString < $rule->starts_on->toDateString()) {
                    continue;
                }
                if ($rule->ends_on !== null && $dateString > $rule->ends_on->toDateString()) {
                    continue;
                }

                $occurrenceStart = CarbonImmutable::parse("{$dateString} {$rule->starts_time}", $timezone);
                $occurrenceEnd = CarbonImmutable::parse("{$dateString} {$rule->ends_time}", $timezone);

                $events[] = [
                    'starts_at' => $occurrenceStart->setTimezone('UTC')->toISOString(),
                    'ends_at' => $occurrenceEnd->setTimezone('UTC')->toISOString(),
                    'reason' => $rule->reason,
                ];
            }
        }

        return $events;
    }

    /**
     * @param  array<string, array{open: string, close: string}>  $openingHours
     * @return array{open: string, close: string}|null
     */
    private function todaysOpeningHours(CarbonImmutable $nowLocal, array $openingHours): ?array
    {
        $dayKey = self::WEEKDAY_KEYS[$nowLocal->dayOfWeek] ?? null;

        if ($dayKey === null || ! isset($openingHours[$dayKey])) {
            return null;
        }

        return $openingHours[$dayKey];
    }
}
