<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request, SettingsService $settings): Response
    {
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

        $events = $query->get();

        $eventTypes = AuditEvent::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->all();

        return Inertia::render('admin/Audit', [
            'events' => $events,
            'eventTypes' => $eventTypes,
            'filters' => [
                'event_type' => $validated['event_type'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
        ]);
    }
}
