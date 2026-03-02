<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Settings\SettingsSchema;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (SettingsSchema::defaults() as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => null],
            );
        }
    }
}
