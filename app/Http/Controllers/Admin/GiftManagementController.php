<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GiftManagementController extends Controller
{
    public function index()
    {
        $gifts = Gift::with('category')->latest()->paginate(20);
        return view('admin.gift-management.index', compact('gifts'));
    }

    public function create()
    {
        $categories = GiftCategory::where('is_active', true)->get();
        return view('admin.gift-management.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gift_category_id' => 'nullable|exists:gift_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:10',
            'background_color' => 'required|string|max:7',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        // Générer un slug unique
        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 1;

        while (Gift::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $validated['slug'] = $slug;

        // Télécharger l'image Twemoji pour l'emoji
        if (!empty($validated['icon'])) {
            $emojiImagePath = $this->downloadTwemojiImage($validated['icon']);
            if ($emojiImagePath) {
                $validated['emoji_image_path'] = $emojiImagePath;
            }
        }

        Gift::create($validated);

        return redirect()->route('admin.gift-management.index')
            ->with('success', 'Cadeau créé avec succès');
    }

    public function edit(Gift $gift)
    {
        $categories = GiftCategory::where('is_active', true)->get();
        return view('admin.gift-management.edit', compact('gift', 'categories'));
    }

    public function update(Request $request, Gift $gift)
    {
        $validated = $request->validate([
            'gift_category_id' => 'nullable|exists:gift_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:10',
            'background_color' => 'required|string|max:7',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        // Générer un nouveau slug si le nom a changé
        if ($validated['name'] !== $gift->name) {
            $slug = Str::slug($validated['name']);
            $baseSlug = $slug;
            $counter = 1;

            while (Gift::where('slug', $slug)->where('id', '!=', $gift->id)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $validated['slug'] = $slug;
        }

        // Si l'icon a changé, télécharger la nouvelle image Twemoji
        if (!empty($validated['icon']) && $validated['icon'] !== $gift->icon) {
            $emojiImagePath = $this->downloadTwemojiImage($validated['icon']);
            if ($emojiImagePath) {
                // Supprimer l'ancienne image si elle existe
                if ($gift->emoji_image_path && Storage::disk('public')->exists($gift->emoji_image_path)) {
                    Storage::disk('public')->delete($gift->emoji_image_path);
                }
                $validated['emoji_image_path'] = $emojiImagePath;
            }
        }

        $gift->update($validated);

        return redirect()->route('admin.gift-management.index')
            ->with('success', 'Cadeau mis à jour avec succès');
    }

    public function destroy(Gift $gift)
    {
        $gift->delete();

        return redirect()->route('admin.gift-management.index')
            ->with('success', 'Cadeau supprimé avec succès');
    }

    /**
     * Télécharge l'image Twemoji pour un emoji donné
     *
     * @param string $emoji L'emoji (ex: 🍫)
     * @return string|null Le chemin relatif de l'image sauvegardée ou null en cas d'échec
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

            Log::info("Converting emoji to Twemoji", [
                'emoji' => $emoji,
                'unicode_hex' => $unicodeHex
            ]);

            // URL de l'image Twemoji (72x72 PNG)
            $twemojiUrl = "https://cdn.jsdelivr.net/gh/twitter/twemoji@latest/assets/72x72/{$unicodeHex}.png";

            // Télécharger l'image
            $response = Http::timeout(10)->get($twemojiUrl);

            if (!$response->successful()) {
                Log::error("Failed to download Twemoji image", [
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

            Log::info("Twemoji image downloaded successfully", [
                'path' => $path,
                'url' => $twemojiUrl
            ]);

            return $path;

        } catch (\Exception $e) {
            Log::error("Error downloading Twemoji image", [
                'emoji' => $emoji,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Régénère l'image Twemoji pour un cadeau
     */
    public function regenerateImage(Gift $gift)
    {
        try {
            // Supprimer l'ancienne image si elle existe
            if ($gift->emoji_image_path) {
                Storage::disk('public')->delete($gift->emoji_image_path);
            }

            // Télécharger la nouvelle image
            $emojiImagePath = $this->downloadTwemojiImage($gift->icon);

            if ($emojiImagePath) {
                $gift->emoji_image_path = $emojiImagePath;
                $gift->save();

                return redirect()
                    ->route('admin.gift-management.index')
                    ->with('success', "L'image Twemoji pour \"{$gift->name}\" a été régénérée avec succès !");
            } else {
                return redirect()
                    ->route('admin.gift-management.index')
                    ->with('error', "Échec de la génération de l'image pour \"{$gift->name}\". Veuillez vérifier que l'emoji est valide.");
            }
        } catch (\Exception $e) {
            Log::error("Error regenerating Twemoji image", [
                'gift_id' => $gift->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->route('admin.gift-management.index')
                ->with('error', "Une erreur est survenue lors de la régénération de l'image.");
        }
    }
}
