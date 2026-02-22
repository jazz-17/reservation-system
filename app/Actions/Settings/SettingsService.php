<?php

namespace App\Actions\Settings;

use App\Actions\Audit\Audit;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;

class SettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $defaults = SettingsDefaults::values();
        $stored = Setting::query()->get()->keyBy('key');

        $merged = $defaults;

        foreach ($stored as $key => $setting) {
            $merged[$key] = $setting->value;
        }

        return $this->normalize($merged);
    }

    public function getString(string $key): string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : (string) $value;
    }

    public function getInt(string $key): int
    {
        $value = $this->get($key);

        return (int) $value;
    }

    public function getBool(string $key): bool
    {
        $value = $this->get($key);

        return (bool) $value;
    }

    public function get(string $key): mixed
    {
        $setting = Setting::query()->find($key);

        if ($setting !== null) {
            return $setting->value;
        }

        return SettingsDefaults::values()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, User $actor): void
    {
        $normalized = $this->normalize($values);
        $defaults = SettingsDefaults::values();

        $changedKeys = [];

        $allowed = array_intersect_key($normalized, $defaults);
        $existing = Setting::query()
            ->whereIn('key', array_keys($allowed))
            ->get()
            ->keyBy('key');

        foreach ($allowed as $key => $value) {
            $previousValue = $existing->get($key)?->value ?? ($defaults[$key] ?? null);

            if ($this->valuesDiffer($previousValue, $value)) {
                $changedKeys[] = $key;
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $actor->id],
            );
        }

        if ($changedKeys !== []) {
            Audit::record('settings.updated', actor: $actor, subject: null, metadata: [
                'changed_keys' => array_values($changedKeys),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalize(array $settings): array
    {
        if (array_key_exists('notify_admin_emails', $settings)) {
            $notify = $settings['notify_admin_emails'];

            if (is_array($notify) && Arr::isList($notify)) {
                $settings['notify_admin_emails'] = ['to' => $notify, 'cc' => [], 'bcc' => []];
            }

            $settings['notify_admin_emails'] = array_merge(
                ['to' => [], 'cc' => [], 'bcc' => []],
                is_array($settings['notify_admin_emails']) ? $settings['notify_admin_emails'] : [],
            );
        }

        return $settings;
    }

    private function valuesDiffer(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) !== json_encode($right);
        }

        return $left !== $right;
    }
}
