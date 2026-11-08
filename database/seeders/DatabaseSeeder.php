<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Full app seed = same as production bootstrap (users + settings + authorities).
        // Fine / process / import data is never seeded.
        $this->call(ProductionBootstrapSeeder::class);
    }
}
