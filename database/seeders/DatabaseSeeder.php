<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            GiftSeeder::class,
            GroupCategorySeeder::class,
        ]);
    }

    /**
     * Seed database with fake data for testing.
     * Run with: php artisan db:seed --class=FakeDataSeeder
     */
    public function runWithFakeData(): void
    {
        $this->call([
            AdminSeeder::class,
            GiftSeeder::class,
            GroupCategorySeeder::class,
            FakeDataSeeder::class,
            AssignGroupCategoriesSeeder::class,
        ]);
    }
}
