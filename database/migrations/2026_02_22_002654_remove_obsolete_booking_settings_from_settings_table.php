<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Setting::query()
            ->whereIn('key', [
                'booking_mode',
                'slot_duration_minutes',
                'slot_step_minutes',
                'predefined_blocks',
            ])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
