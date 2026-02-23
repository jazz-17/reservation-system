<?php

namespace Database\Seeders;

use App\Models\Enums\UserRole;
use App\Models\Faculty;
use App\Models\ProfessionalSchool;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $defaultPassword = Hash::make('password');

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => UserRole::Admin,
                'professional_school_id' => null,
                'base_year' => null,
                'password' => $defaultPassword,
            ],
        );

        $students = [
            ['email' => 'test@example.com', 'first_name' => 'Test', 'last_name' => 'User'],
            ['email' => 'estudiante@example.com', 'first_name' => 'Estudiante', 'last_name' => 'Uno'],
            ['email' => 'estudiante2@example.com', 'first_name' => 'Estudiante', 'last_name' => 'Dos'],
        ];

        foreach ($students as $student) {
            User::query()->updateOrCreate(
                ['email' => $student['email']],
                [
                    'name' => "{$student['first_name']} {$student['last_name']}",
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'role' => UserRole::Student,
                    'professional_school_id' => $systemsSchool->id,
                    'base_year' => 2022,
                    'password' => $defaultPassword,
                ],
            );
        }
    }
}
