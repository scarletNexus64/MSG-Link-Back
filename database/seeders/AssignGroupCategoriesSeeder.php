<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupCategory;
use Illuminate\Database\Seeder;

class AssignGroupCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Assigns random categories to existing groups
     */
    public function run(): void
    {
        $categories = GroupCategory::active()->get();

        if ($categories->isEmpty()) {
            $this->command->warn('No categories found. Please run GroupCategorySeeder first.');
            return;
        }

        $categoryIds = $categories->pluck('id')->toArray();
        $groups = Group::whereNull('category_id')->get();

        if ($groups->isEmpty()) {
            $this->command->info('No groups without categories found.');
            return;
        }

        $this->command->info("Assigning categories to {$groups->count()} groups...");

        foreach ($groups as $group) {
            $group->update([
                'category_id' => $categoryIds[array_rand($categoryIds)]
            ]);
        }

        $this->command->info('Group categories assigned successfully!');
    }
}
