<?php

namespace App\Actions\Settings;

use App\Actions\Audit\Audit;
use App\Models\Setting;
use App\Models\User;
use App\Settings\Exceptions\MissingSettingsException;
use App\Settings\SettingsSchema;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->load();
    }

    public function getString(string $key): string
    {
        $value = $this->getRequired($key);

        return is_string($value) ? $value : (string) $value;
    }

    public function getInt(string $key): int
    {
        $value = $this->getRequired($key);

        return (int) $value;
    }

    public function getBool(string $key): bool
    {
        $value = $this->getRequired($key);

        return (bool) $value;
    }

    public function get(string $key): mixed
    {
        return $this->getRequired($key);
    }

    public function getRequired(string $key): mixed
    {
        $values = $this->load();

        if (! array_key_exists($key, $values)) {
            throw new MissingSettingsException(
                missingKeys: [$key],
                message: sprintf('Missing required settings key: %s. Run `php artisan settings:sync`.', $key),
            );
        }

        return $values[$key];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, User $actor): void
    {
        $normalized = SettingsSchema::validate($values);
        $allowedKeys = array_flip(SettingsSchema::keys());

        $changedKeys = [];

        $allowed = array_intersect_key($normalized, $allowedKeys);
        $existing = Setting::query()
            ->whereIn('key', array_keys($allowed))
            ->get()
            ->keyBy('key');

        foreach ($allowed as $key => $value) {
            $previousValue = $existing->get($key)?->value;

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

        $this->cache = null;
    }

    public function resetToDefaults(User $actor): void
    {
        $defaults = SettingsSchema::defaults();
        $validated = SettingsSchema::validate($defaults);

        DB::transaction(function () use ($validated, $actor): void {
            foreach ($validated as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $actor->id],
                );
            }
        });

        Audit::record('settings.reset_to_defaults', actor: $actor, subject: null, metadata: [
            'keys' => array_keys($validated),
        ]);

        $this->cache = null;
    }

    private function valuesDiffer(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) !== json_encode($right);
        }

        return $left !== $right;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $keys = SettingsSchema::keys();

        $stored = Setting::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $missing = [];
        $values = [];

        foreach ($keys as $key) {
            $setting = $stored->get($key);

            if ($setting === null) {
                $missing[] = $key;

                continue;
            }

            $values[$key] = $setting->value;
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

        $this->cache = SettingsSchema::validate($values);

        return $this->cache;
    }
}
