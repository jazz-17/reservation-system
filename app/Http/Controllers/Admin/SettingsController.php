<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(SettingsService $settings): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => $settings->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request, SettingsService $settings): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $settings->setMany($request->validated(), $user);

        return back()->with('success', 'Configuración actualizada.');
    }

    public function reset(Request $request, SettingsService $settings): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $settings->resetToDefaults($user);

        return back()->with('success', 'Configuración restablecida a valores por defecto.');
    }
}
