<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_no_overlap EXCLUDE USING gist (tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status = 'approved')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_no_overlap');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_no_overlap EXCLUDE USING gist (tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status IN ('pending','approved'))");
    }
};
