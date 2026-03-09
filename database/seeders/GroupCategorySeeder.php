<?php

namespace Database\Seeders;

use App\Models\GroupCategory;
use Illuminate\Database\Seeder;

class GroupCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Technologie',
                'slug' => 'technologie',
                'icon' => 'computer',
                'color' => '#3B82F6',
                'description' => 'Groupes dédiés aux nouvelles technologies, programmation, gadgets et innovations',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'icon' => 'sports_soccer',
                'color' => '#10B981',
                'description' => 'Discussions sur tous les sports, compétitions et activités physiques',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Musique',
                'slug' => 'musique',
                'icon' => 'music_note',
                'color' => '#8B5CF6',
                'description' => 'Partagez votre passion pour la musique, artistes et genres musicaux',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Éducation',
                'slug' => 'education',
                'icon' => 'school',
                'color' => '#F59E0B',
                'description' => 'Apprentissage, formation, cours et partage de connaissances',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Gaming',
                'slug' => 'gaming',
                'icon' => 'sports_esports',
                'color' => '#EF4444',
                'description' => 'Jeux vidéo, e-sport, stratégies et communautés de gamers',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Arts & Culture',
                'slug' => 'arts-culture',
                'icon' => 'palette',
                'color' => '#EC4899',
                'description' => 'Arts visuels, cinéma, littérature et événements culturels',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Voyages',
                'slug' => 'voyages',
                'icon' => 'flight',
                'color' => '#14B8A6',
                'description' => 'Destinations, conseils de voyage et découverte du monde',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Cuisine',
                'slug' => 'cuisine',
                'icon' => 'restaurant',
                'color' => '#F97316',
                'description' => 'Recettes, gastronomie et partage de plats délicieux',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Bien-être',
                'slug' => 'bien-etre',
                'icon' => 'spa',
                'color' => '#06B6D4',
                'description' => 'Santé, fitness, méditation et développement personnel',
                'is_active' => true,
                'sort_order' => 9,
            ],
            [
                'name' => 'Autre',
                'slug' => 'autre',
                'icon' => 'more_horiz',
                'color' => '#6B7280',
                'description' => 'Tous les autres sujets et discussions générales',
                'is_active' => true,
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $category) {
            GroupCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $this->command->info('GroupCategories seeded successfully!');
    }
}
