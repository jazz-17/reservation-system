<?php

namespace App\Console\Commands;

use App\Actions\Audit\Audit;
use App\Models\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-admin {email : Email of the existing user to promote}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote an existing user to admin role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = Str::lower((string) $this->argument('email'));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        if ($user->role === UserRole::Admin) {
            $this->info("User is already admin: {$email}");

            return self::SUCCESS;
        }

        $user->forceFill(['role' => UserRole::Admin])->save();

        Audit::record('user.role_promoted', actor: null, subject: $user, metadata: [
            'email' => $email,
            'role' => 'admin',
        ]);

        $this->info("Promoted to admin: {$email}");

        return self::SUCCESS;
    }
}
