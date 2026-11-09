<?php

namespace App\Console\Commands;

use App\Models\Authority;
use App\Models\EmailSetting;
use App\Models\User;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel Cloud deploy entrypoint.
 * Migrates schema, then seeds users + email settings + authorities only.
 */
class CloudDeploy extends Command
{
    protected $signature = 'vio:cloud-deploy';

    protected $description = 'Cloud deploy: migrate --force + seed users/settings/authorities (no fine data).';

    public function handle(): int
    {
        $this->info('1/2 migrate --force');
        $migrate = Artisan::call('migrate', ['--force' => true]);
        $this->output->write(Artisan::output());
        if ($migrate !== self::SUCCESS) {
            $this->error('migrate failed');

            return $migrate;
        }

        $this->info('2/2 ProductionBootstrapSeeder (users + settings + authorities)');
        $this->call('db:seed', [
            '--class' => ProductionBootstrapSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->table(
            ['Check', 'Value'],
            [
                ['users', Schema::hasTable('users') ? (string) User::query()->count() : 'n/a'],
                ['authorities', Schema::hasTable('authorities') ? (string) Authority::query()->count() : 'n/a'],
                ['email_settings', Schema::hasTable('email_settings') ? (string) EmailSetting::query()->count() : 'n/a'],
                ['VIO_BOOTSTRAP_PASSWORD', filled(env('VIO_BOOTSTRAP_PASSWORD')) ? 'set' : 'MISSING (new users skipped)'],
            ]
        );

        if (Schema::hasTable('users') && User::query()->count() === 0) {
            $this->warn('No users in DB. Set VIO_BOOTSTRAP_PASSWORD in Cloud Variables and re-run: php artisan vio:cloud-deploy');
        } else {
            $this->info('Cloud deploy bootstrap OK.');
        }

        return self::SUCCESS;
    }
}
