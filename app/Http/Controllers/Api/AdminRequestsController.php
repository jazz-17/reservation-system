<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRequestsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $reservations = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->with([
                'user:id,name,email,first_name,last_name,phone,professional_school_id,base_year',
                'user.professionalSchool:id,faculty_id,name',
                'user.professionalSchool.faculty:id,name',
            ])
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => $reservations,
        ]);
    }
}
