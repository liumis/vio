<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Display name for the admin user}
        {--email= : Login email (existing user with this email will be updated)}
        {--password= : Plain-text password (you will be prompted if omitted)}';

    protected $description = 'Create or update an admin user (idempotent by email).';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name', 'Admin');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $this->info(sprintf(
            '%s admin user: %s <%s> (id %d).',
            $existing ? 'Updated' : 'Created',
            $user->name,
            $user->email,
            $user->id,
        ));

        return self::SUCCESS;
    }
}
