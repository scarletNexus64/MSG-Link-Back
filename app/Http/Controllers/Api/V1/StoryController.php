<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Models\PremiumSubscription;
use App\Models\Story;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StoryController extends Controller
{
    /**
     * Feed des stories actives
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Marquer les stories expirées
        Story::markExpiredStories();

        // Récupérer les stories actives groupées par utilisateur
        $stories = Story::active()
            ->with('user:id,first_name,last_name,username,avatar')
            ->orderBy('created_at', 'desc')
            ->get();

        // Grouper par utilisateur et ajouter le flag "viewed"
        $storiesByUser = $stories->groupBy('user_id')->map(function ($userStories) use ($user) {
            $firstStory = $userStories->first();
            $allViewed = $userStories->every(function ($story) use ($user) {
                return $story->isViewedBy($user);
            });

            // Si l'utilisateur créateur de la story est supprimé, ne pas afficher la story
            if (!$firstStory->user) {
                return null;
            }

            // Vérifier si l'utilisateur a payé pour voir l'identité
            $isOwner = $user && $user->id === $firstStory->user_id;
            $hasSubscription = $user && PremiumSubscription::hasActiveForStory($user->id, $firstStory->id);
            // Les utilisateurs avec Premium Pass actif voient tous les vrais noms
            $hasPremiumPass = $user && $user->is_premium && $user->premium_expires_at && $user->premium_expires_at->isFuture();
            $shouldRevealIdentity = $isOwner || $hasSubscription || $hasPremiumPass;

            // Préparer l'aperçu de la première story
            $preview = [
                'type' => $firstStory->type,
            ];

            if ($firstStory->type === 'image') {
                $preview['media_url'] = $firstStory->media_full_url;
            } elseif ($firstStory->type === 'video') {
                $preview['media_url'] = $firstStory->media_full_url;
                $preview['thumbnail_url'] = $firstStory->thumbnail_full_url;
            } elseif ($firstStory->type === 'text') {
                $preview['content'] = $firstStory->content;
                $preview['background_color'] = $firstStory->background_color;
            }

            return [
                'user' => [
                    'id' => $shouldRevealIdentity ? $firstStory->user->id : null,
                    'username' => $shouldRevealIdentity ? $firstStory->user->username : 'Anonyme',
                    'full_name' => $shouldRevealIdentity ? $firstStory->user->full_name : 'Utilisateur Anonyme',
                    'avatar_url' => $shouldRevealIdentity ? $firstStory->user->avatar_url : 'https://ui-avatars.com/api/?name=Anonyme&background=667eea&color=fff',
                ],
                'real_user_id' => $firstStory->user->id, // Toujours retourner l'ID réel pour pouvoir charger les stories
                'is_anonymous' => !$shouldRevealIdentity,
                'is_owner' => $isOwner,
                'preview' => $preview,
                'stories_count' => $userStories->count(),
                'latest_story_at' => $firstStory->created_at->toIso8601String(),
                'all_viewed' => $allViewed,
                'has_new' => !$allViewed,
            ];
        })->filter()->values(); // Filtrer les valeurs null

        // Trier : mes stories en premier, puis non vues, puis vues
        $storiesByUser = $storiesByUser->sort(function ($a, $b) use ($user) {
            // Mes stories toujours en premier
            if ($a['is_owner'] && !$b['is_owner']) return -1;
            if (!$a['is_owner'] && $b['is_owner']) return 1;

            // Ensuite, stories non vues avant stories vues
            if ($a['has_new'] && !$b['has_new']) return -1;
            if (!$a['has_new'] && $b['has_new']) return 1;

            // Sinon, par date de création (plus récent en premier)
            return strtotime($b['latest_story_at']) - strtotime($a['latest_story_at']);
        })->values();

        return response()->json([
            'stories' => $storiesByUser,
        ]);
    }

    /**
     * Stories d'un utilisateur spécifique par username
     */
    public function userStories(Request $request, string $username): JsonResponse
    {
        $viewer = $request->user();

        $user = User::where('username', $username)->withoutTrashed()->first();

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        return $this->fetchUserStoriesByIdInternal($request, $user->id);
    }

    /**
     * Stories d'un utilisateur spécifique par ID
     */
    public function userStoriesById(Request $request, int $userId): JsonResponse
    {
        return $this->fetchUserStoriesByIdInternal($request, $userId);
    }

    /**
     * Méthode privée pour récupérer les stories d'un utilisateur par ID
     */
    private function fetchUserStoriesByIdInternal(Request $request, int $userId): JsonResponse
    {
        $viewer = $request->user();

        $user = User::withoutTrashed()->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        // Récupérer les stories actives de l'utilisateur
        $stories = Story::forUser($user->id)
            ->active()
            ->orderBy('created_at', 'asc')
            ->get();

        // Ajouter le flag "viewed" pour chaque story
        $stories->transform(function ($story) use ($viewer) {
            $story->is_viewed = $story->isViewedBy($viewer);
            return $story;
        });

        // Vérifier si on doit révéler l'identité
        $isOwner = $viewer && $viewer->id === $user->id;
        $hasSubscription = $viewer && $stories->isNotEmpty() &&
            PremiumSubscription::hasActiveForStory($viewer->id, $stories->first()->id);
        // Les utilisateurs avec Premium Pass actif voient tous les vrais noms
        $hasPremiumPass = $viewer && $viewer->is_premium && $viewer->premium_expires_at && $viewer->premium_expires_at->isFuture();
        $shouldRevealIdentity = $isOwner || $hasSubscription || $hasPremiumPass;

        return response()->json([
            'user' => [
                'id' => $shouldRevealIdentity ? $user->id : null,
                'username' => $shouldRevealIdentity ? $user->username : 'Anonyme',
                'full_name' => $shouldRevealIdentity ? $user->full_name : 'Utilisateur Anonyme',
                'avatar_url' => $shouldRevealIdentity ? $user->avatar_url : 'https://ui-avatars.com/api/?name=Anonyme&background=667eea&color=fff',
            ],
            'is_anonymous' => !$shouldRevealIdentity,
            'stories' => StoryResource::collection($stories),
        ]);
    }

    /**
     * Mes stories
     */
    public function myStories(Request $request): JsonResponse
    {
        $user = $request->user();

        $stories = Story::forUser($user->id)
            ->with('viewedBy:id,first_name,last_name,username,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'stories' => StoryResource::collection($stories),
            'meta' => [
                'current_page' => $stories->currentPage(),
                'last_page' => $stories->lastPage(),
                'per_page' => $stories->perPage(),
                'total' => $stories->total(),
            ],
        ]);
    }

    /**
     * Détail d'une story
     */
    public function show(Request $request, Story $story): JsonResponse
    {
        $user = $request->user();

        if ($story->is_expired) {
            return response()->json([
                'message' => 'Cette story a expiré.',
            ], 404);
        }

        // Marquer comme vue (sauf pour le propriétaire)
        if ($story->user_id !== $user->id) {
            $story->markAsViewedBy($user);
        }

        $story->load('user:id,first_name,last_name,username,avatar');
        $story->is_viewed = $story->isViewedBy($user);

        return response()->json([
            'story' => new StoryResource($story),
        ]);
    }

    /**
     * Créer une story
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Log la requête pour debugging
        \Log::info('📸 [STORY] Création de story');
        \Log::info('Type: ' . $request->input('type'));
        \Log::info('Has media file: ' . ($request->hasFile('media') ? 'YES' : 'NO'));
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            \Log::info('Media details:', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'error' => $file->getError(),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:image,text,video',
            'media' => 'required_if:type,image|required_if:type,video|file|max:102400', // 100MB max pour vidéos
            'content' => 'required_if:type,text|nullable|string|max:500', // Requis pour texte, optionnel pour légende d'image
            'background_color' => 'nullable|string|max:7', // Format hex: #RRGGBB
            'duration' => 'nullable|integer|min:3|max:300', // 3 secondes à 5 minutes (300 sec)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $storyData = [
            'user_id' => $user->id,
            'type' => $validated['type'],
            'duration' => $validated['duration'] ?? 5,
            'expires_at' => now()->addHours(24), // Expire après 24h
        ];

        // Gestion du média (image ou vidéo)
        if (in_array($validated['type'], ['image', 'video']) && $request->hasFile('media')) {
            $media = $request->file('media');

            // Valider le type MIME selon le type
            if ($validated['type'] === 'image') {
                if (!in_array($media->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    return response()->json([
                        'message' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP).',
                    ], 422);
                }
            } elseif ($validated['type'] === 'video') {
                if (!in_array($media->getMimeType(), ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'])) {
                    return response()->json([
                        'message' => 'Le fichier doit être une vidéo (MP4, MOV, AVI, WebM).',
                    ], 422);
                }
            }

            // Sauvegarder le média
            $path = $media->store('stories/' . $user->id, 'public');
            $storyData['media_url'] = $path;

            // Gérer le thumbnail pour les vidéos
            if ($validated['type'] === 'video' && $request->hasFile('thumbnail')) {
                \Log::info('📸 [STORY] Thumbnail vidéo reçu');
                $thumbnail = $request->file('thumbnail');

                // Valider que c'est bien une image
                if (in_array($thumbnail->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
                    $thumbnailPath = $thumbnail->store('stories/' . $user->id . '/thumbnails', 'public');
                    $storyData['thumbnail_url'] = $thumbnailPath;
                    \Log::info('✅ [STORY] Thumbnail sauvegardé: ' . $thumbnailPath);
                } else {
                    \Log::warning('⚠️ [STORY] Format de thumbnail non supporté: ' . $thumbnail->getMimeType());
                }
            }

            // Ajouter la légende si présente
            if (!empty($validated['content'])) {
                $storyData['content'] = $validated['content'];
            }
        }

        // Gestion du contenu texte
        if ($validated['type'] === 'text') {
            $storyData['content'] = $validated['content'];
            $storyData['background_color'] = $validated['background_color'] ?? '#6366f1';
        }

        $story = Story::create($storyData);

        // Envoyer notification par topic
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendNewStoryTopicNotification($story->load('user'));
            \Log::info('📢 Notification topic envoyée pour nouvelle story', [
                'story_id' => $story->id
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Erreur notification topic story: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Story créée avec succès.',
            'story' => new StoryResource($story->load('user')),
        ], 201);
    }

    /**
     * Supprimer une story
     */
    public function destroy(Request $request, Story $story): JsonResponse
    {
        $user = $request->user();

        // Seul le propriétaire peut supprimer
        if ($story->user_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Supprimer le média du stockage
        if ($story->media_url) {
            Storage::disk('public')->delete($story->media_url);
        }

        // Supprimer le thumbnail du stockage
        if ($story->thumbnail_url) {
            Storage::disk('public')->delete($story->thumbnail_url);
        }

        $story->delete();

        return response()->json([
            'message' => 'Story supprimée avec succès.',
        ]);
    }

    /**
     * Marquer une story comme vue
     */
    public function markAsViewed(Request $request, Story $story): JsonResponse
    {
        $user = $request->user();

        if ($story->is_expired) {
            return response()->json([
                'message' => 'Cette story a expiré.',
            ], 404);
        }

        // Ne pas compter les vues du propriétaire
        if ($story->user_id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas voir votre propre story.',
            ], 422);
        }

        $viewed = $story->markAsViewedBy($user);

        return response()->json([
            'message' => $viewed ? 'Story vue.' : 'Déjà vue.',
            'views_count' => $story->views_count,
        ]);
    }

    /**
     * Obtenir les viewers d'une story
     */
    public function viewers(Request $request, Story $story): JsonResponse
    {
        $user = $request->user();

        // Seul le propriétaire peut voir les viewers
        if ($story->user_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $viewers = $story->getViewers();

        return response()->json([
            'viewers' => $viewers->map(fn($viewer) => [
                'id' => $viewer->id,
                'username' => $viewer->username,
                'full_name' => $viewer->full_name,
                'avatar_url' => $viewer->avatar_url,
                'viewed_at' => $viewer->pivot->created_at->toIso8601String(),
                'has_liked' => $story->isLikedBy($viewer),
            ]),
            'total_views' => $story->views_count,
        ]);
    }

    /**
     * Statistiques des stories
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalStories = Story::forUser($user->id)->count();
        $activeStories = Story::forUser($user->id)->active()->count();
        $totalViews = Story::forUser($user->id)->sum('views_count');

        return response()->json([
            'total_stories' => $totalStories,
            'active_stories' => $activeStories,
            'expired_stories' => $totalStories - $activeStories,
            'total_views' => $totalViews,
        ]);
    }

    /**
     * Liker une story
     */
    public function like(Request $request, Story $story): JsonResponse
    {
        $user = $request->user();

        // Vérifier que la story est active
        if ($story->is_expired) {
            return response()->json([
                'message' => 'Cette story a expiré.',
            ], 404);
        }

        // Vérifier que ce n'est pas sa propre story
        if ($story->user_id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas liker votre propre story.',
            ], 422);
        }

        // Liker la story
        $liked = $story->likeBy($user);

        if (!$liked) {
            return response()->json([
                'message' => 'Vous avez déjà liké cette story.',
            ], 422);
        }

        // Envoyer une notification FCM au créateur de la story (sauf si c'est lui-même)
        if ($story->user_id !== $user->id) {
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->sendStoryLikeNotification($story);
            } catch (\Exception $e) {
                \Log::warning('Story like notification failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Story likée avec succès.',
        ]);
    }

    /**
     * Répondre à une story
     */
    public function reply(Request $request, Story $story): JsonResponse
    {
        $user = $request->user();

        // Valider la requête
        $validator = \Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Message invalide.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Vérifier que la story est active
        if ($story->is_expired) {
            return response()->json([
                'message' => 'Cette story a expiré.',
            ], 404);
        }

        // Vérifier que ce n'est pas sa propre story
        if ($story->user_id === $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas répondre à votre propre story.',
            ], 422);
        }

        // Récupérer le créateur de la story
        $storyOwner = $story->user;

        if (!$storyOwner) {
            return response()->json([
                'message' => 'Utilisateur non trouvé.',
            ], 404);
        }

        // Vérifier les blocages
        if ($user->isBlockedBy($storyOwner) || $user->hasBlocked($storyOwner)) {
            return response()->json([
                'message' => 'Impossible de répondre à cette story.',
            ], 422);
        }

        try {
            \DB::beginTransaction();

            // Créer ou récupérer la conversation
            $conversation = $user->getOrCreateConversationWith($storyOwner);

            // Créer le message de réponse à la story
            $message = \App\Models\ChatMessage::createStoryReplyMessage(
                $conversation,
                $user,
                $story,
                $request->input('message')
            );

            // Mettre à jour la conversation
            $conversation->updateAfterMessage();

            \DB::commit();

            // Diffuser l'événement de message
            try {
                $message->load(['sender', 'story']);
                event(new \App\Events\ChatMessageSent($message, $storyOwner->id));
            } catch (\Exception $e) {
                \Log::warning('Broadcasting failed for story reply: ' . $e->getMessage());
            }

            // Envoyer une notification FCM au créateur de la story
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->sendStoryReplyNotification($story, $message);
            } catch (\Exception $e) {
                \Log::warning('Story reply notification failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Réponse envoyée avec succès.',
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::error('Error replying to story: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'envoi de la réponse.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
