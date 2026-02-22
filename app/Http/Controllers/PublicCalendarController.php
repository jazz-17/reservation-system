<?php

namespace App\Http\Controllers;

use App\Actions\Settings\SettingsService;
use Inertia\Inertia;
use Inertia\Response;

class PublicCalendarController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SettingsService $settings): Response
    {
        return Inertia::render('calendar/Public', [
            'timezone' => $settings->getString('timezone'),
        ]);
    }
}
