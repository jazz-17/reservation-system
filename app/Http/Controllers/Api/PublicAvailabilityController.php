<?php

namespace App\Http\Controllers\Api;

use App\Actions\Reservations\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicAvailabilityRequest;
use Illuminate\Http\JsonResponse;

class PublicAvailabilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(PublicAvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $availability->eventsForRange($validated['start'], $validated['end']),
        );
    }
}
