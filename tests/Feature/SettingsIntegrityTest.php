<?php

use App\Models\Setting;
use App\Settings\Exceptions\InvalidSettingsException;
use App\Settings\Exceptions\MissingSettingsException;
use App\Settings\SettingsIntegrity;

test('settings integrity fails when a required key is missing', function () {
    Setting::query()->whereKey('timezone')->delete();

    expect(fn () => app(SettingsIntegrity::class)->assertReady())
        ->toThrow(MissingSettingsException::class);
});

test('settings integrity fails when a stored value is invalid', function () {
    Setting::query()->updateOrCreate(
        ['key' => 'min_duration_minutes'],
        ['value' => 0, 'updated_by' => null],
    );

    expect(fn () => app(SettingsIntegrity::class)->assertReady())
        ->toThrow(InvalidSettingsException::class);
});
