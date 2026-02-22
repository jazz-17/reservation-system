<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['professional_school', 'base']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('professional_school_id')->constrained('professional_schools')->restrictOnDelete();
            $table->smallInteger('base_year');

            $table->index(['professional_school_id', 'base_year', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['professional_school_id', 'base_year', 'starts_at']);
            $table->dropConstrainedForeignId('professional_school_id');
            $table->dropColumn(['base_year']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('professional_school');
            $table->string('base');
        });
    }
};
