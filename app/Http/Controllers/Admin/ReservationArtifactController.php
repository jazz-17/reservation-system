<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReservationPdf;
use App\Jobs\SendReservationEmail;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\ReservationArtifact;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReservationArtifactController extends Controller
{
    public function index(): Response
    {
        $artifacts = ReservationArtifact::query()
            ->where('status', ReservationArtifactStatus::Failed)
            ->with(['reservation.user'])
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return Inertia::render('admin/Artifacts', [
            'artifacts' => $artifacts,
        ]);
    }

    public function retry(ReservationArtifact $artifact): RedirectResponse
    {
        if ($artifact->kind === ReservationArtifactKind::Pdf) {
            GenerateReservationPdf::dispatch($artifact->id);
        } else {
            SendReservationEmail::dispatch($artifact->id);
        }

        return back()->with('success', 'Reintento encolado.');
    }
}
