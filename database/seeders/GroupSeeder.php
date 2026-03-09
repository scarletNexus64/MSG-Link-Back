<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\GroupCategory;
use App\Models\User;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les catégories
        $categories = GroupCategory::all()->keyBy('slug');

        // Récupérer des utilisateurs pour créer les groupes
        $users = User::inRandomOrder()->limit(50)->get();

        if ($users->isEmpty()) {
            $this->command->error('Aucun utilisateur trouvé. Veuillez d\'abord créer des utilisateurs.');
            return;
        }

        $groupsData = [
            // TECHNOLOGIE - Catégorie 1
            [
                'name' => 'Coding Warriors',
                'description' => 'Communauté de développeurs passionnés. Partagez vos projets, posez vos questions et progressez ensemble.',
                'category' => 'technologie',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'AI & Machine Learning',
                'description' => 'Groupe privé pour les experts en IA et ML. Discussions avancées sur les dernières innovations.',
                'category' => 'technologie',
                'is_public' => false,
                'max_members' => 50,
            ],
            [
                'name' => 'Web Dev Pro',
                'description' => 'Front-end, back-end, full-stack ? Tous les développeurs web sont les bienvenus !',
                'category' => 'technologie',
                'is_public' => true,
                'max_members' => 80,
            ],

            // SPORTS - Catégorie 2
            [
                'name' => 'Football Fans',
                'description' => 'Discutez de vos équipes favorites, des matchs et de l\'actualité football.',
                'category' => 'sports',
                'is_public' => true,
                'max_members' => 150,
            ],
            [
                'name' => 'Fitness Gang',
                'description' => 'Motivation, conseils nutrition et programmes d\'entraînement. Devenez la meilleure version de vous-même !',
                'category' => 'sports',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Running Club Elite',
                'description' => 'Club privé pour coureurs expérimentés. Préparation marathon et ultra-trail.',
                'category' => 'sports',
                'is_public' => false,
                'max_members' => 40,
            ],

            // MUSIQUE - Catégorie 3
            [
                'name' => 'Music Lovers',
                'description' => 'De la pop au rock, du jazz au rap. Partagez vos playlists et découvertes musicales.',
                'category' => 'musique',
                'is_public' => true,
                'max_members' => 120,
            ],
            [
                'name' => 'Electronic Vibes',
                'description' => 'EDM, House, Techno, Trance... Groupe pour les amateurs de musique électronique.',
                'category' => 'musique',
                'is_public' => true,
                'max_members' => 80,
            ],
            [
                'name' => 'Musicians Network',
                'description' => 'Réseau privé de musiciens professionnels. Collaborations et opportunités.',
                'category' => 'musique',
                'is_public' => false,
                'max_members' => 50,
            ],

            // ÉDUCATION - Catégorie 4
            [
                'name' => 'Study Group',
                'description' => 'Entraide scolaire et universitaire. Partagez vos notes, révisez ensemble.',
                'category' => 'education',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Language Exchange',
                'description' => 'Pratiquez les langues étrangères avec des natifs du monde entier.',
                'category' => 'education',
                'is_public' => true,
                'max_members' => 150,
            ],
            [
                'name' => 'MBA Students',
                'description' => 'Groupe privé pour étudiants en MBA. Ressources, networking et opportunités.',
                'category' => 'education',
                'is_public' => false,
                'max_members' => 60,
            ],

            // GAMING - Catégorie 5
            [
                'name' => 'Game Zone',
                'description' => 'Console, PC, mobile ? Tous les gamers sont bienvenus. LFG et discussions gaming.',
                'category' => 'gaming',
                'is_public' => true,
                'max_members' => 200,
            ],
            [
                'name' => 'Esports Arena',
                'description' => 'Communauté compétitive. Tournois, stratégies et actualités esports.',
                'category' => 'gaming',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Pro Gamers Only',
                'description' => 'Groupe privé pour joueurs professionnels et semi-pro. Scrims et coaching.',
                'category' => 'gaming',
                'is_public' => false,
                'max_members' => 30,
            ],

            // BUSINESS / AUTRE - Catégorie 10
            [
                'name' => 'Entrepreneurs Hub',
                'description' => 'Réseau d\'entrepreneurs. Échangez idées, conseils et opportunités business.',
                'category' => 'autre',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Startup Founders',
                'description' => 'Communauté de fondateurs de startups. Pitch, fundraising et growth hacking.',
                'category' => 'autre',
                'is_public' => true,
                'max_members' => 80,
            ],
            [
                'name' => 'Investment Club',
                'description' => 'Groupe privé d\'investisseurs. Analyses de marché et opportunités d\'investissement.',
                'category' => 'autre',
                'is_public' => false,
                'max_members' => 50,
            ],
            [
                'name' => 'Crypto Traders',
                'description' => 'Trading crypto, NFT et DeFi. Analyses techniques et signaux.',
                'category' => 'autre',
                'is_public' => false,
                'max_members' => 60,
            ],

            // DIVERTISSEMENT / AUTRE - Catégorie 10
            [
                'name' => 'Movie Night',
                'description' => 'Cinéphiles, critiques et recommandations de films et séries.',
                'category' => 'autre',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Anime Fans',
                'description' => 'Discussions sur les animes, mangas et culture japonaise.',
                'category' => 'autre',
                'is_public' => true,
                'max_members' => 120,
            ],
            [
                'name' => 'Memes Factory',
                'description' => 'Les meilleurs memes du web. Rires garantis !',
                'category' => 'autre',
                'is_public' => true,
                'max_members' => 200,
            ],

            // ART & CRÉATIVITÉ - Catégorie 6
            [
                'name' => 'Photography Club',
                'description' => 'Partagez vos photos, techniques et inspirations photographiques.',
                'category' => 'arts-culture',
                'is_public' => true,
                'max_members' => 80,
            ],
            [
                'name' => 'Art Collective',
                'description' => 'Artistes de tous horizons : peinture, sculpture, digital art.',
                'category' => 'arts-culture',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Writers Circle',
                'description' => 'Groupe privé d\'écrivains. Critiques constructives et ateliers d\'écriture.',
                'category' => 'arts-culture',
                'is_public' => false,
                'max_members' => 40,
            ],

            // BIEN-ÊTRE - Catégorie 9
            [
                'name' => 'Yoga & Wellness',
                'description' => 'Bien-être physique et mental. Yoga, méditation et développement personnel.',
                'category' => 'bien-etre',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Mental Health Support',
                'description' => 'Groupe de soutien bienveillant. Parlons de santé mentale sans tabou.',
                'category' => 'bien-etre',
                'is_public' => true,
                'max_members' => 80,
            ],
            [
                'name' => 'Meditation Masters',
                'description' => 'Groupe privé pour pratiquants avancés de méditation.',
                'category' => 'bien-etre',
                'is_public' => false,
                'max_members' => 30,
            ],

            // VOYAGE - Catégorie 7
            [
                'name' => 'Travel Buddies',
                'description' => 'Partagez vos voyages, conseils et trouvez des compagnons d\'aventure.',
                'category' => 'voyages',
                'is_public' => true,
                'max_members' => 150,
            ],
            [
                'name' => 'Digital Nomads',
                'description' => 'Travailler en voyageant. Destinations, visas et remote work.',
                'category' => 'voyages',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Luxury Travelers',
                'description' => 'Groupe privé pour voyageurs haut de gamme. Expériences exclusives.',
                'category' => 'voyages',
                'is_public' => false,
                'max_members' => 50,
            ],

            // AUTRES GROUPES POPULAIRES
            [
                'name' => 'Foodies United',
                'description' => 'Passionnés de cuisine et gastronomie. Recettes, restaurants et food spots.',
                'category' => 'cuisine',
                'is_public' => true,
                'max_members' => 120,
            ],
            [
                'name' => 'Book Club',
                'description' => 'Un livre par mois, des discussions passionnantes.',
                'category' => 'education',
                'is_public' => true,
                'max_members' => 80,
            ],
            [
                'name' => 'Pet Lovers',
                'description' => 'Pour tous ceux qui aiment les animaux. Photos mignonnes et conseils.',
                'category' => 'autre',
                'is_public' => true,
                'max_members' => 150,
            ],
            [
                'name' => 'Fashion Squad',
                'description' => 'Mode, style et tendances. Partagez vos looks et inspirations.',
                'category' => 'arts-culture',
                'is_public' => true,
                'max_members' => 100,
            ],
            [
                'name' => 'Science Geeks',
                'description' => 'Passionnés de sciences. Physique, chimie, astronomie et plus.',
                'category' => 'education',
                'is_public' => true,
                'max_members' => 80,
            ],
        ];

        $this->command->info('Création des groupes...');
        $createdCount = 0;

        foreach ($groupsData as $groupData) {
            $creator = $users->random();
            $category = $categories->get($groupData['category']);

            if (!$category) {
                $this->command->warn("Catégorie '{$groupData['category']}' non trouvée pour le groupe '{$groupData['name']}'");
                continue;
            }

            // Créer le groupe
            $group = Group::create([
                'name' => $groupData['name'],
                'description' => $groupData['description'],
                'category_id' => $category->id,
                'creator_id' => $creator->id,
                'invite_code' => Group::generateInviteCode(),
                'is_public' => $groupData['is_public'],
                'is_discoverable' => $groupData['is_public'] ? true : false, // Groupes privés non découvrables par défaut
                'max_members' => $groupData['max_members'],
                'members_count' => 1,
            ]);

            // Ajouter le créateur comme admin
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $creator->id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Message système de bienvenue
            GroupMessage::create([
                'group_id' => $group->id,
                'sender_id' => $creator->id,
                'content' => "Groupe créé par Anonyme",
                'type' => 'system',
            ]);

            // Ajouter des membres aléatoires (entre 5 et 20)
            $memberCount = rand(5, min(20, $groupData['max_members'] - 1));
            $potentialMembers = $users->where('id', '!=', $creator->id)->random(min($memberCount, $users->count() - 1));

            foreach ($potentialMembers as $member) {
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $member->id,
                    'role' => 'member',
                    'joined_at' => now()->subDays(rand(1, 30)),
                ]);

                $group->increment('members_count');
            }

            // Ajouter quelques messages aléatoires
            $messageCount = rand(3, 15);
            $groupMembers = $group->activeMembers()->with('user')->get();

            for ($i = 0; $i < $messageCount; $i++) {
                $sender = $groupMembers->random();
                GroupMessage::create([
                    'group_id' => $group->id,
                    'sender_id' => $sender->user_id,
                    'content' => $this->getRandomMessage(),
                    'type' => 'text',
                    'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]);
            }

            // Mettre à jour last_message_at et messages_count
            $group->update([
                'last_message_at' => $group->messages()->latest()->first()?->created_at,
                'messages_count' => $group->messages()->count(),
            ]);

            $createdCount++;
            $visibility = $groupData['is_public'] ? '🌐 PUBLIC' : '🔒 PRIVÉ';
            $this->command->info("✓ {$group->name} ({$visibility}) - {$group->members_count} membres");
        }

        $this->command->info("\n✅ {$createdCount} groupes créés avec succès!");
    }

    /**
     * Messages aléatoires pour les groupes
     */
    private function getRandomMessage(): string
    {
        $messages = [
            "Bienvenue à tous !",
            "Super groupe, merci de m'avoir accepté",
            "Quelqu'un a des recommandations ?",
            "Je suis d'accord avec toi",
            "Excellente idée !",
            "Merci pour le partage",
            "C'est vraiment intéressant",
            "Je ne savais pas ça",
            "Quelqu'un peut m'aider ?",
            "Voici mon avis sur la question",
            "Je cherche des conseils",
            "Merci à tous pour votre aide",
            "Hâte de discuter avec vous",
            "Belle initiative !",
            "Je suis nouveau ici, ravi de vous rencontrer",
        ];

        return $messages[array_rand($messages)];
    }
}
