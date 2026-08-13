<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate:fresh --seed` populates the local demo (DoD §12):
     * demo user demo@menutag.test / password, code-generated logos, saved QR
     * presets and a menu-tag history across statuses and size bands.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
