<?php

namespace Database\Seeders;

use App\Models\Gift;
use App\Models\GiftCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les catégories
        $fleurs = GiftCategory::where('slug', 'fleurs')->first();
        $fun = GiftCategory::where('slug', 'fun')->first();
        $sucreries = GiftCategory::where('slug', 'sucreries')->first();
        $liquides = GiftCategory::where('slug', 'liquides')->first();
        $luxe = GiftCategory::where('slug', 'luxe')->first();

        $gifts = [
            // ========== BRONZE (1000 FCFA) - 12 cadeaux ==========
            ['name' => 'Cœur', 'slug' => 'coeur', 'description' => 'Un petit cœur pour montrer ton affection', 'icon' => '❤️', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ff6b6b', 'gift_category_id' => $fun?->id, 'sort_order' => 1],
            ['name' => 'Rose', 'slug' => 'rose', 'description' => 'Une rose romantique', 'icon' => '🌹', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ff0040', 'gift_category_id' => $fleurs?->id, 'sort_order' => 2],
            ['name' => 'Étoile', 'slug' => 'etoile', 'description' => 'Une étoile brillante', 'icon' => '⭐', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ffd93d', 'gift_category_id' => $fun?->id, 'sort_order' => 3],
            ['name' => 'Bonbon', 'slug' => 'bonbon', 'description' => 'Un délicieux bonbon sucré', 'icon' => '🍬', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ff87ab', 'gift_category_id' => $sucreries?->id, 'sort_order' => 4],
            ['name' => 'Fleur', 'slug' => 'fleur', 'description' => 'Une jolie fleur', 'icon' => '🌸', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ffb7d5', 'gift_category_id' => $fleurs?->id, 'sort_order' => 5],
            ['name' => 'Café', 'slug' => 'cafe', 'description' => 'Un café chaud', 'icon' => '☕', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#6f4e37', 'gift_category_id' => $liquides?->id, 'sort_order' => 6],
            ['name' => 'Cookie', 'slug' => 'cookie', 'description' => 'Un cookie délicieux', 'icon' => '🍪', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#d2691e', 'gift_category_id' => $sucreries?->id, 'sort_order' => 7],
            ['name' => 'Donut', 'slug' => 'donut', 'description' => 'Un donut sucré', 'icon' => '🍩', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ffb6c1', 'gift_category_id' => $sucreries?->id, 'sort_order' => 8],
            ['name' => 'Ballon', 'slug' => 'ballon', 'description' => 'Un ballon festif', 'icon' => '🎈', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ff6347', 'gift_category_id' => $fun?->id, 'sort_order' => 9],
            ['name' => 'Cœur Bleu', 'slug' => 'coeur-bleu', 'description' => 'Un cœur bleu', 'icon' => '💙', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#4169e1', 'gift_category_id' => $fun?->id, 'sort_order' => 10],
            ['name' => 'Cerise', 'slug' => 'cerise', 'description' => 'Des cerises fraîches', 'icon' => '🍒', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#dc143c', 'gift_category_id' => $sucreries?->id, 'sort_order' => 11],
            ['name' => 'Fraise', 'slug' => 'fraise', 'description' => 'Une fraise juteuse', 'icon' => '🍓', 'price' => 1000, 'tier' => Gift::TIER_BRONZE, 'background_color' => '#ff0800', 'gift_category_id' => $sucreries?->id, 'sort_order' => 12],

            // ========== SILVER (5000 FCFA) - 12 cadeaux ==========
            ['name' => 'Chocolat', 'slug' => 'chocolat', 'description' => 'Une boîte de chocolats délicieux', 'icon' => '🍫', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#8b4513', 'gift_category_id' => $sucreries?->id, 'sort_order' => 13],
            ['name' => 'Tulipe', 'slug' => 'tulipe', 'description' => 'Une belle tulipe colorée', 'icon' => '🌷', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#ff69b4', 'gift_category_id' => $fleurs?->id, 'sort_order' => 14],
            ['name' => 'Feu d\'artifice', 'slug' => 'feu-artifice', 'description' => 'Un magnifique feu d\'artifice', 'icon' => '🎆', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#4a0e4e', 'gift_category_id' => $fun?->id, 'sort_order' => 15],
            ['name' => 'Cocktail', 'slug' => 'cocktail', 'description' => 'Un cocktail rafraîchissant', 'icon' => '🍹', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#ff6b9d', 'gift_category_id' => $liquides?->id, 'sort_order' => 16],
            ['name' => 'Hibiscus', 'slug' => 'hibiscus', 'description' => 'Une fleur d\'hibiscus tropicale', 'icon' => '🌺', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#ff1493', 'gift_category_id' => $fleurs?->id, 'sort_order' => 17],
            ['name' => 'Vin', 'slug' => 'vin', 'description' => 'Un verre de vin rouge', 'icon' => '🍷', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#722f37', 'gift_category_id' => $liquides?->id, 'sort_order' => 18],
            ['name' => 'Cupcake', 'slug' => 'cupcake', 'description' => 'Un cupcake décoré', 'icon' => '🧁', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#ffb6d9', 'gift_category_id' => $sucreries?->id, 'sort_order' => 19],
            ['name' => 'Glace', 'slug' => 'glace', 'description' => 'Une glace rafraîchissante', 'icon' => '🍦', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#e0f6ff', 'gift_category_id' => $sucreries?->id, 'sort_order' => 20],
            ['name' => 'Pizza', 'slug' => 'pizza', 'description' => 'Une part de pizza', 'icon' => '🍕', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#ffa500', 'gift_category_id' => $sucreries?->id, 'sort_order' => 21],
            ['name' => 'Papillon', 'slug' => 'papillon', 'description' => 'Un papillon coloré', 'icon' => '🦋', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#9370db', 'gift_category_id' => $fun?->id, 'sort_order' => 22],
            ['name' => 'Arc-en-ciel', 'slug' => 'arc-en-ciel', 'description' => 'Un magnifique arc-en-ciel', 'icon' => '🌈', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#ff69b4', 'gift_category_id' => $fun?->id, 'sort_order' => 23],
            ['name' => 'Bière', 'slug' => 'biere', 'description' => 'Une bière fraîche', 'icon' => '🍺', 'price' => 5000, 'tier' => Gift::TIER_SILVER, 'background_color' => '#f4a460', 'gift_category_id' => $liquides?->id, 'sort_order' => 24],

            // ========== GOLD (25000 FCFA) - 12 cadeaux ==========
            ['name' => 'Bouquet de fleurs', 'slug' => 'bouquet', 'description' => 'Un magnifique bouquet de fleurs', 'icon' => '💐', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#ff1493', 'gift_category_id' => $fleurs?->id, 'sort_order' => 25],
            ['name' => 'Champagne', 'slug' => 'champagne', 'description' => 'Une bouteille de champagne', 'icon' => '🍾', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#d4af37', 'gift_category_id' => $liquides?->id, 'sort_order' => 26],
            ['name' => 'Gâteau', 'slug' => 'gateau', 'description' => 'Un délicieux gâteau d\'anniversaire', 'icon' => '🎂', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#ff69b4', 'gift_category_id' => $sucreries?->id, 'sort_order' => 27],
            ['name' => 'Trophée', 'slug' => 'trophee', 'description' => 'Un trophée de champion', 'icon' => '🏆', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#ffd700', 'gift_category_id' => $fun?->id, 'sort_order' => 28],
            ['name' => 'Cadeau', 'slug' => 'cadeau-luxe', 'description' => 'Un cadeau emballé luxueusement', 'icon' => '🎁', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#ff4757', 'gift_category_id' => $fun?->id, 'sort_order' => 29],
            ['name' => 'Sushi Premium', 'slug' => 'sushi', 'description' => 'Un plateau de sushi premium', 'icon' => '🍣', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#ff6348', 'gift_category_id' => $sucreries?->id, 'sort_order' => 30],
            ['name' => 'Martini', 'slug' => 'martini', 'description' => 'Un martini élégant', 'icon' => '🍸', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#20bf6b', 'gift_category_id' => $liquides?->id, 'sort_order' => 31],
            ['name' => 'Fête', 'slug' => 'fete', 'description' => 'Une célébration festive', 'icon' => '🎉', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#fd79a8', 'gift_category_id' => $fun?->id, 'sort_order' => 32],
            ['name' => 'Étoile Brillante', 'slug' => 'etoile-brillante', 'description' => 'Une étoile scintillante', 'icon' => '✨', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#fdcb6e', 'gift_category_id' => $fun?->id, 'sort_order' => 33],
            ['name' => 'Orchidée', 'slug' => 'orchidee', 'description' => 'Une orchidée exotique rare', 'icon' => '🌼', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#a29bfe', 'gift_category_id' => $fleurs?->id, 'sort_order' => 34],
            ['name' => 'Couronne de Fleurs', 'slug' => 'couronne-fleurs', 'description' => 'Une couronne de fleurs délicate', 'icon' => '💮', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#fab1a0', 'gift_category_id' => $fleurs?->id, 'sort_order' => 35],
            ['name' => 'Cognac', 'slug' => 'cognac', 'description' => 'Un cognac de collection', 'icon' => '🥃', 'price' => 25000, 'tier' => Gift::TIER_GOLD, 'background_color' => '#8b4513', 'gift_category_id' => $liquides?->id, 'sort_order' => 36],

            // ========== DIAMOND (50000 FCFA) - 12 cadeaux ==========
            ['name' => 'Bague diamant', 'slug' => 'bague-diamant', 'description' => 'Une bague sertie de diamants', 'icon' => '💍', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#b9f2ff', 'gift_category_id' => $luxe?->id, 'sort_order' => 37],
            ['name' => 'Couronne', 'slug' => 'couronne', 'description' => 'Une couronne royale', 'icon' => '👑', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#ffd700', 'gift_category_id' => $luxe?->id, 'sort_order' => 38],
            ['name' => 'Diamant', 'slug' => 'diamant', 'description' => 'Un diamant étincelant', 'icon' => '💎', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#00d9ff', 'gift_category_id' => $luxe?->id, 'sort_order' => 39],
            ['name' => 'Sunflower Bouquet', 'slug' => 'sunflower-bouquet', 'description' => 'Un bouquet de tournesols lumineux', 'icon' => '🌻', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#ffdb58', 'gift_category_id' => $fleurs?->id, 'sort_order' => 40],
            ['name' => 'Rocket', 'slug' => 'rocket', 'description' => 'Une fusée vers les étoiles', 'icon' => '🚀', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#e84118', 'gift_category_id' => $fun?->id, 'sort_order' => 41],
            ['name' => 'Étoile Filante', 'slug' => 'etoile-filante', 'description' => 'Une étoile filante magique', 'icon' => '🌠', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#0c2461', 'gift_category_id' => $fun?->id, 'sort_order' => 42],
            ['name' => 'Licorne', 'slug' => 'licorne', 'description' => 'Une licorne magique', 'icon' => '🦄', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#e056fd', 'gift_category_id' => $fun?->id, 'sort_order' => 43],
            ['name' => 'Dragon', 'slug' => 'dragon', 'description' => 'Un dragon légendaire', 'icon' => '🐉', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#b33939', 'gift_category_id' => $fun?->id, 'sort_order' => 44],
            ['name' => 'Château', 'slug' => 'chateau', 'description' => 'Un château de conte de fées', 'icon' => '🏰', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#6c5ce7', 'gift_category_id' => $luxe?->id, 'sort_order' => 45],
            ['name' => 'Voiture de Sport', 'slug' => 'voiture-sport', 'description' => 'Une voiture de sport de luxe', 'icon' => '🏎️', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#e74c3c', 'gift_category_id' => $luxe?->id, 'sort_order' => 46],
            ['name' => 'Yacht', 'slug' => 'yacht', 'description' => 'Un yacht de luxe', 'icon' => '🛥️', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#3498db', 'gift_category_id' => $luxe?->id, 'sort_order' => 47],
            ['name' => 'Avion Privé', 'slug' => 'avion-prive', 'description' => 'Un avion privé', 'icon' => '✈️', 'price' => 50000, 'tier' => Gift::TIER_DIAMOND, 'background_color' => '#95a5a6', 'gift_category_id' => $luxe?->id, 'sort_order' => 48],
        ];

        foreach ($gifts as $giftData) {
            // Télécharger l'image Twemoji
            $emojiImagePath = $this->downloadTwemojiImage($giftData['icon']);
            if ($emojiImagePath) {
                $giftData['emoji_image_path'] = $emojiImagePath;
            }

            Gift::updateOrCreate(
                ['slug' => $giftData['slug']],
                $giftData
            );

            $this->command->info("✅ Cadeau créé: {$giftData['name']} ({$giftData['icon']})");
        }

        $this->command->info('🎉 Tous les cadeaux ont été créés avec leurs images Twemoji!');
    }

    /**
     * Télécharge l'image Twemoji pour un emoji donné
     */
    private function downloadTwemojiImage(string $emoji): ?string
    {
        try {
            // Convertir l'emoji en code Unicode hexadécimal
            $codepoints = [];
            $runes = mb_str_split($emoji);

            foreach ($runes as $rune) {
                $codepoint = dechex(mb_ord($rune));
                $codepoints[] = $codepoint;
            }

            $unicodeHex = implode('-', $codepoints);

            // URL de l'image Twemoji (72x72 PNG)
            $twemojiUrl = "https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/72x72/{$unicodeHex}.png";

            // Télécharger l'image
            $response = Http::timeout(10)->get($twemojiUrl);

            if (!$response->successful()) {
                $this->command->warn("⚠️  Échec téléchargement image pour {$emoji}");
                return null;
            }

            // Créer le dossier emojis s'il n'existe pas
            $directory = 'public/emojis';
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Nom du fichier: unicode_hex.png
            $filename = "{$unicodeHex}.png";
            $path = "emojis/{$filename}";

            // Sauvegarder l'image
            Storage::disk('public')->put($path, $response->body());

            return $path;

        } catch (\Exception $e) {
            $this->command->error("❌ Erreur pour {$emoji}: {$e->getMessage()}");
            return null;
        }
    }
}
