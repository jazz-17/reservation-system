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
        Schema::create('allow_list_entries', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->uuid('import_batch_id')->nullable()->index();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('email');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        Schema::table('allow_list_entries', function (Blueprint $table) {
            $table->dropUnique('allow_list_entries_email_unique');
        });

        DB::statement('ALTER TABLE allow_list_entries ALTER COLUMN email TYPE citext');

        Schema::table('allow_list_entries', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allow_list_entries');
    }
};
