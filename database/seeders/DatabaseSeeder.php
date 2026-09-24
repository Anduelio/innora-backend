<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            HotelSeeder::class,
            UserSeeder::class,
            InventoryCatalogSeeder::class,
            ChargeCategorySeeder::class,
        ]);

        Artisan::call('passport:setup', ['--force' => true]);
        $this->command?->info(trim(Artisan::output()));
    }
}
