<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production bootstrap: users + settings + authorities.
 * Does NOT seed fines / process logs / imports / activity.
 */
class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductionUsersSeeder::class,
            ProductionEmailSettingsSeeder::class,
            AuthoritySeeder::class,
        ]);
    }
}
