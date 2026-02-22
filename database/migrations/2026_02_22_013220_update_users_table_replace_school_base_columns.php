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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['professional_school', 'base']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('professional_school_id')->nullable()->constrained('professional_schools')->restrictOnDelete();
            $table->smallInteger('base_year')->nullable();

            $table->index(['professional_school_id', 'base_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['professional_school_id', 'base_year']);
            $table->dropConstrainedForeignId('professional_school_id');
            $table->dropColumn(['base_year']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('professional_school')->nullable();
            $table->string('base')->nullable();
        });
    }
};
