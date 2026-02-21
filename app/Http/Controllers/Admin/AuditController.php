<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(): Response
    {
        $events = AuditEvent::query()
            ->latest('created_at')
            ->limit(300)
            ->get();

        return Inertia::render('admin/Audit', [
            'events' => $events,
        ]);
    }
}
