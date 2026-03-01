<?php

namespace App\Console\Commands;

use App\Actions\Audit\Audit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AssignRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-role {email : Email of the existing user} {role : Role to assign (replaces existing roles)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a Spatie role to an existing user (replaces existing roles)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));
        $roleName = Str::lower(trim((string) $this->argument('role')));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        $roleExists = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->exists();

        if (! $roleExists) {
            $available = Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name')
                ->implode(', ');

            $this->error("Role not found: {$roleName}");
            $this->line("Available roles: {$available}");

            return self::FAILURE;
        }

        $previousRoles = $user->getRoleNames()->all();

        $user->syncRoles([$roleName]);

        Audit::record('user.role_assigned', actor: null, subject: $user, metadata: [
            'email' => $email,
            'role' => $roleName,
            'previous_roles' => $previousRoles,
        ]);

        $this->info("Assigned role '{$roleName}' to: {$email}");

        return self::SUCCESS;
    }
}
