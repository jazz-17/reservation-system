<?php

namespace Database\Seeders;

use App\Models\Enums\UserRole;
use App\Models\Faculty;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SettingsSeeder::class);

        $systemsFaculty = Faculty::query()->updateOrCreate(
            ['name' => 'Facultad de Ingeniería de Sistemas'],
            ['active' => true],
        );

        $schoolMin = 2018;
        $schoolMax = max($schoolMin, (int) now()->year);

        $systemsSchool = ProfessionalSchool::query()->updateOrCreate(
            ['faculty_id' => $systemsFaculty->id, 'name' => 'E.P. Sistemas'],
            ['code' => 'ep_sistemas', 'active' => true, 'base_year_min' => $schoolMin, 'base_year_max' => $schoolMax],
        );

        ProfessionalSchool::query()->updateOrCreate(
            ['faculty_id' => $systemsFaculty->id, 'name' => 'E.P. Software'],
            ['code' => 'ep_software', 'active' => true, 'base_year_min' => $schoolMin, 'base_year_max' => $schoolMax],
        );

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'role' => UserRole::Admin,
            'professional_school_id' => null,
            'base_year' => null,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => UserRole::Student,
            'professional_school_id' => $systemsSchool->id,
            'base_year' => 2022,
        ]);
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'estudiante@example.com',
            'role' => UserRole::Student,
            'professional_school_id' => $systemsSchool->id,
            'base_year' => 2022,
        ]);
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'estudiante2@example.com',
            'role' => UserRole::Student,
            'professional_school_id' => $systemsSchool->id,
            'base_year' => 2022,
        ]);
    }
}
