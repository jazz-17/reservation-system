<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\Audit;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateReservationPdf;
use App\Jobs\SendReservationEmail;
use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use App\Models\ReservationArtifact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function retry(Request $request, ReservationArtifact $artifact): RedirectResponse
    {
        $actor = $request->user();
        if ($actor !== null) {
            Audit::record('artifact.retried', actor: $actor, subject: $artifact, metadata: [
                'reservation_id' => $artifact->reservation_id,
                'kind' => $artifact->kind->value,
            ]);
        }

        if ($artifact->kind === ReservationArtifactKind::Pdf) {
            GenerateReservationPdf::dispatch($artifact->id);
        } else {
            SendReservationEmail::dispatch($artifact->id);
        }

        return back()->with('success', 'Reintento encolado.');
    }
}
