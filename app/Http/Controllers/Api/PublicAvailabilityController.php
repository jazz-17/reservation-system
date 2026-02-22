<?php

namespace App\Http\Controllers\Api;

use App\Actions\Reservations\AvailabilityService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAvailabilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        return response()->json(
            $availability->eventsForRange($validated['start'], $validated['end']),
        );
    }
}
