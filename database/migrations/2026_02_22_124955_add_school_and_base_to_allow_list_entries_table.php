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
        Schema::table('allow_list_entries', function (Blueprint $table) {
            $table->foreignId('professional_school_id')
                ->nullable()
                ->after('email')
                ->constrained('professional_schools')
                ->restrictOnDelete();
            $table->smallInteger('base_year')->nullable()->after('professional_school_id');

            $table->index(['professional_school_id', 'base_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('allow_list_entries', function (Blueprint $table) {
            $table->dropIndex(['professional_school_id', 'base_year']);
            $table->dropConstrainedForeignId('professional_school_id');
            $table->dropColumn(['base_year']);
        });
    }
};
