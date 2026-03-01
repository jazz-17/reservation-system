<?php

namespace Database\Seeders;

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

        $this->call(RolesAndPermissionsSeeder::class);

        $adminEmail = (string) config('seed.admin.email');
        $adminPassword = (string) config('seed.admin.password');
        $adminFirstName = (string) config('seed.admin.first_name');
        $adminLastName = (string) config('seed.admin.last_name');

        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => "{$adminFirstName} {$adminLastName}",
                'first_name' => $adminFirstName,
                'last_name' => $adminLastName,
                'professional_school_id' => null,
                'base_year' => null,
                'password' => Hash::make($adminPassword),
            ],
        );

        $admin->syncRoles(['admin']);
    }
}
