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
        Schema::create('professional_schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();
            $table->string('name');
            $table->smallInteger('base_year_min');
            $table->smallInteger('base_year_max');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['faculty_id', 'name']);
            $table->index(['faculty_id', 'active']);
            $table->index(['faculty_id', 'base_year_min', 'base_year_max']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE professional_schools ADD CONSTRAINT professional_schools_base_year_range CHECK (base_year_max >= base_year_min)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_schools');
    }
};
