<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_blackouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('weekday')->index();
            $table->time('starts_time');
            $table->time('ends_time');
            $table->date('starts_on')->nullable()->index();
            $table->date('ends_on')->nullable()->index();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE recurring_blackouts ADD CONSTRAINT recurring_blackouts_weekday_range CHECK (weekday >= 0 AND weekday <= 6)');
        DB::statement('ALTER TABLE recurring_blackouts ADD CONSTRAINT recurring_blackouts_ends_after_starts CHECK (ends_time > starts_time)');
        DB::statement('ALTER TABLE recurring_blackouts ADD CONSTRAINT recurring_blackouts_date_range CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_blackouts');
    }
};
