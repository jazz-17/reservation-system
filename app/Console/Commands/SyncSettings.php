<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Settings\SettingsSchema;
use Illuminate\Console\Command;

class SyncSettings extends Command
{
    protected $signature = 'settings:sync {--force : Overwrite existing settings with hardcoded defaults}';

    protected $description = 'Ensure all required settings keys exist in the database';

    public function handle(): int
    {
        $defaults = SettingsSchema::defaults();
        $force = (bool) $this->option('force');

        $existingKeys = Setting::query()
            ->whereIn('key', array_keys($defaults))
            ->pluck('key')
            ->all();

        $existingKeys = array_flip($existingKeys);

        $created = 0;
        $updated = 0;

        foreach ($defaults as $key => $value) {
            $exists = array_key_exists($key, $existingKeys);

            if ($exists && ! $force) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => null],
            );

            if ($exists) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->info('Settings sync complete.');
        $this->line("Created: {$created}");
        $this->line("Updated: {$updated}");
        $this->line('Tip: use --force to overwrite existing values.');

        return self::SUCCESS;
    }
}
