<?php

namespace App\Http\Controllers\Api;

use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminHistoryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SettingsService $settings): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $timezone = $settings->getString('timezone');

        $query = Reservation::query()
            ->with(['user:id,name,email,professional_school,base'])
            ->latest('starts_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['from'])) {
            $fromUtc = CarbonImmutable::parse($validated['from'], $timezone)->startOfDay()->setTimezone('UTC');
            $query->where('starts_at', '>=', $fromUtc);
        }

        if (! empty($validated['to'])) {
            $toUtc = CarbonImmutable::parse($validated['to'], $timezone)->endOfDay()->setTimezone('UTC');
            $query->where('starts_at', '<=', $toUtc);
        }

        return response()->json([
            'data' => $query->limit(500)->get(),
        ]);
    }
}
