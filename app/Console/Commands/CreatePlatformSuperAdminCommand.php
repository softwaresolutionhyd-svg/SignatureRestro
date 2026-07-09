<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreatePlatformSuperAdminCommand extends Command
{
    protected $signature = 'platform:create-super-admin {email} {password}';

    protected $description = 'Create or upgrade a user to platform super_admin (no company; manages all tenants)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->argument('password');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->update([
                'role' => 'super_admin',
                'company_id' => null,
                'password' => $password,
                'permissions' => null,
            ]);
            $this->info("Updated existing user to super_admin: {$email}");
        } else {
            User::query()->create([
                'name' => 'Platform Super Admin',
                'email' => $email,
                'password' => $password,
                'role' => 'super_admin',
                'company_id' => null,
                'email_verified_at' => now(),
                'permissions' => null,
            ]);
            $this->info("Created super_admin: {$email}");
        }

        $this->line('Login, then open /companies/select to choose a company.');

        return self::SUCCESS;
    }
}
