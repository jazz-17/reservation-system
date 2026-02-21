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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('professional_school')->nullable();
            $table->string('base')->nullable();
            $table->string('phone')->nullable();
            $table->string('role')->default('student')->index();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });

            DB::statement('ALTER TABLE users ALTER COLUMN email TYPE varchar(255)');

            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'professional_school',
                'base',
                'phone',
                'role',
            ]);
        });
    }
};
