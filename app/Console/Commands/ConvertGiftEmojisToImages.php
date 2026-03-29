<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Gift;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConvertGiftEmojisToImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gifts:convert-emojis {--force : Force la re-téléchargement même si l\'image existe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convertit les emojis des cadeaux en images Twemoji';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Début de la conversion des emojis en images Twemoji...');
        $this->newLine();

        // Récupérer tous les cadeaux
        $gifts = Gift::all();
        $totalGifts = $gifts->count();
        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $this->info("📦 {$totalGifts} cadeaux trouvés");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalGifts);
        $progressBar->start();

        foreach ($gifts as $gift) {
            // Si l'image existe déjà et qu'on ne force pas, on skip
            if ($gift->emoji_image_path && !$this->option('force')) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            // Télécharger l'image Twemoji
            $emojiImagePath = $this->downloadTwemojiImage($gift->icon);

            if ($emojiImagePath) {
                $gift->emoji_image_path = $emojiImagePath;
                $gift->save();
                $converted++;
            } else {
                $failed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Résumé
        $this->info('✅ Conversion terminée!');
        $this->newLine();
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['Convertis', $converted],
                ['Ignorés (déjà convertis)', $skipped],
                ['Échecs', $failed],
                ['Total', $totalGifts],
            ]
        );

        return Command::SUCCESS;
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
                Log::warning("Échec téléchargement Twemoji pour {$emoji}", [
                    'url' => $twemojiUrl,
                    'status' => $response->status()
                ]);
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

            Log::info("Image Twemoji téléchargée avec succès", [
                'emoji' => $emoji,
                'path' => $path
            ]);

            return $path;

        } catch (\Exception $e) {
            Log::error("Erreur téléchargement Twemoji", [
                'emoji' => $emoji,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
