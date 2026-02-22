<?php

namespace App\Http\Controllers\Student;

use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(SettingsService $settings): Response
    {
        return Inertia::render('calendar/Index', [
            'timezone' => $settings->getString('timezone'),
        ]);
    }
}
