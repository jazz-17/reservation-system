<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->whereKey('timezone')->delete();
    }

    public function down(): void
    {
        //
    }
};
