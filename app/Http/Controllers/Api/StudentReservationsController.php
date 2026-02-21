<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentReservationsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $reservations = Reservation::query()
            ->where('user_id', $user->id)
            ->latest('starts_at')
            ->get([
                'id',
                'status',
                'starts_at',
                'ends_at',
                'decision_reason',
                'cancellation_reason',
                'created_at',
            ]);

        return response()->json([
            'data' => $reservations,
        ]);
    }
}
