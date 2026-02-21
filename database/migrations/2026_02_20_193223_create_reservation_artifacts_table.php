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
        Schema::create('reservation_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->index();
            $table->string('status')->index()->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_artifacts');
    }
};
