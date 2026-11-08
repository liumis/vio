<?php

namespace App\Console\Commands;

use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Idempotent prod bootstrap after migrate:
 * users + system settings + authorities (no fine/process data).
 */
class BootstrapProduction extends Command
{
    protected $signature = 'vio:bootstrap-production
        {--migrate : Run php artisan migrate --force first}
        {--optimize-clear : Run optimize:clear after seed}';

    protected $description = 'Seed production users, system settings and authorities (no fine data).';

    public function handle(): int
    {
        if ($this->option('migrate')) {
            $this->info('Running migrations…');
            $code = Artisan::call('migrate', ['--force' => true]);
            $this->output->write(Artisan::output());
            if ($code !== self::SUCCESS) {
                return $code;
            }
        }

        $this->info('Seeding ProductionBootstrapSeeder…');
        $this->callSilent('db:seed', [
            '--class' => ProductionBootstrapSeeder::class,
            '--force' => true,
        ]);
        $this->output->write(Artisan::output());

        if ($this->option('optimize-clear')) {
            Artisan::call('optimize:clear');
            $this->output->write(Artisan::output());
        }

        $this->info('Bootstrap complete.');

        return self::SUCCESS;
    }
}
