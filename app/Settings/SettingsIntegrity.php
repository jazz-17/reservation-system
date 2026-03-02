<?php

namespace App\Settings;

use App\Models\Setting;
use App\Settings\Exceptions\InvalidSettingsException;
use App\Settings\Exceptions\MissingSettingsException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SettingsIntegrity
{
    public function assertReady(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $keys = SettingsSchema::keys();

        $settings = Setting::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $missing = [];

        foreach ($keys as $key) {
            if (! $settings->has($key)) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new MissingSettingsException(
                missingKeys: $missing,
                message: sprintf(
                    'Missing required settings keys: %s. Run `php artisan settings:sync`.',
                    implode(', ', $missing),
                ),
            );
        }

        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $settings->get($key)?->value;
        }

        $values = SettingsSchema::normalize($values);

        $validator = Validator::make($values, SettingsSchema::rules());

        if ($validator->fails()) {
            throw new InvalidSettingsException(
                errors: $validator->errors()->toArray(),
                message: 'Invalid settings detected. Fix via Admin UI or run `php artisan settings:sync --force`.',
            );
        }
    }
}
