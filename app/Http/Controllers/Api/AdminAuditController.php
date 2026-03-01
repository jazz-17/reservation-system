<?php

namespace App\Http\Controllers\Api;

use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings): JsonResponse
    {
        if (! $request->user()?->can('admin.supervision.auditoria.view')) {
            abort(403);
        }

        $validated = $request->validate([
            'event_type' => ['nullable', 'string', 'max:128'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $timezone = $settings->getString('timezone');

        $query = AuditEvent::query()
            ->with(['actor:id,name'])
            ->latest('created_at')
            ->limit(300);

        if (! empty($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }

        if (! empty($validated['from'])) {
            $fromUtc = CarbonImmutable::parse($validated['from'], $timezone)->startOfDay()->setTimezone('UTC');
            $query->where('created_at', '>=', $fromUtc);
        }

        if (! empty($validated['to'])) {
            $toUtc = CarbonImmutable::parse($validated['to'], $timezone)->endOfDay()->setTimezone('UTC');
            $query->where('created_at', '<=', $toUtc);
        }

        $eventTypes = AuditEvent::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->all();

        return response()->json([
            'data' => $query->get(),
            'eventTypes' => $eventTypes,
        ]);
    }
}
