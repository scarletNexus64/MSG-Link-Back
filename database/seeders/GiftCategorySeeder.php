<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\GiftCategory;
use Illuminate\Support\Str;

class GiftCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Fleurs',
                'slug' => 'fleurs',
                'description' => 'Bouquets et fleurs romantiques',
                'is_active' => true,
            ],
            [
                'name' => 'Fun',
                'slug' => 'fun',
                'description' => 'Cadeaux amusants et festifs',
                'is_active' => true,
            ],
            [
                'name' => 'Sucreries',
                'slug' => 'sucreries',
                'description' => 'Chocolats et friandises',
                'is_active' => true,
            ],
            [
                'name' => 'Liquides',
                'slug' => 'liquides',
                'description' => 'Boissons et cocktails',
                'is_active' => true,
            ],
            [
                'name' => 'Luxe',
                'slug' => 'luxe',
                'description' => 'Cadeaux premium et luxueux',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            GiftCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $this->command->info('✅ Catégories de cadeaux créées avec succès!');
    }
}
