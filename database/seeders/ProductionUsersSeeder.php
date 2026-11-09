<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException; // used when reset-passwords is requested without a password

/**
 * Prod/operators users only (idempotent by email).
 * Password: VIO_BOOTSTRAP_PASSWORD (required when creating a missing user).
 * Existing users keep their password unless VIO_BOOTSTRAP_RESET_PASSWORDS=true.
 */
class ProductionUsersSeeder extends Seeder
{
    /**
     * @return list<array{name:string,email:string}>
     */
    public static function users(): array
    {
        return [
            ['name' => 'Admin', 'email' => 'administration@greenlease.lt'],
            ['name' => 'Agneska', 'email' => 'agneska@sitandgo.lt'],
            ['name' => 'Mykolas', 'email' => 'mykolas@sitandgo.lt'],
            ['name' => 'Liumis', 'email' => 'liumis@liumis.com'],
        ];
    }

    public function run(): void
    {
        // Must use config() — env() is null after config:cache on Cloud.
        $password = trim((string) config('vio.bootstrap_password', ''));
        $reset = (bool) config('vio.bootstrap_reset_passwords', false);

        foreach (self::users() as $row) {
            $existing = User::query()->where('email', $row['email'])->first();

            if ($existing === null) {
                if ($password === '') {
                    // Do not fail the whole Cloud deploy — settings/authorities can still seed.
                    $this->command?->warn(
                        'Skipped creating '.$row['email']
                        .' (set VIO_BOOTSTRAP_PASSWORD in Laravel Cloud env, then re-run vio:bootstrap-production).'
                    );

                    continue;
                }

                User::query()->create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);

                $this->command?->info('Created user '.$row['email']);

                continue;
            }

            $payload = ['name' => $row['name']];
            if ($reset) {
                if ($password === '') {
                    throw new RuntimeException('VIO_BOOTSTRAP_PASSWORD required when VIO_BOOTSTRAP_RESET_PASSWORDS=true.');
                }
                $payload['password'] = Hash::make($password);
            }

            $existing->fill($payload)->save();
            $this->command?->info(
                $reset
                    ? 'Updated user + password '.$row['email']
                    : 'Ensured user '.$row['email']
            );
        }
    }
}
