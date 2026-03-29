<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Confession\CreateConfessionRequest;
use App\Http\Requests\Confession\ReportConfessionRequest;
use App\Http\Resources\ConfessionResource;
use App\Models\Confession;
use App\Models\ConfessionComment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ConfessionController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Feed des confessions publiques approuvées
     */
    public function index(Request $request): JsonResponse
    {
        $confessions = Confession::publicApproved()
            ->with('author:id,first_name,last_name,username,avatar,is_premium,premium_expires_at')
            ->withCount(['comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Ajouter le flag "liked" pour l'utilisateur connecté
        if ($request->user()) {
            $confessions->getCollection()->transform(function ($confession) use ($request) {
                $confession->is_liked = $confession->isLikedBy($request->user());
                return $confession;
            });
        } else {
            // Si pas connecté, is_liked = false
            $confessions->getCollection()->transform(function ($confession) {
                $confession->is_liked = false;
                return $confession;
            });
        }

        return response()->json([
            'confessions' => ConfessionResource::collection($confessions),
            'meta' => [
                'current_page' => $confessions->currentPage(),
                'last_page' => $confessions->lastPage(),
                'per_page' => $confessions->perPage(),
                'total' => $confessions->total(),
            ],
        ]);
    }

    /**
     * Mes confessions reçues (privées)
     */
    public function received(Request $request): JsonResponse
    {
        $user = $request->user();

        $confessions = Confession::forRecipient($user->id)
            ->private()
            ->with('author:id,first_name,last_name,username,avatar,is_premium,premium_expires_at')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'confessions' => ConfessionResource::collection($confessions),
            'meta' => [
                'current_page' => $confessions->currentPage(),
                'last_page' => $confessions->lastPage(),
                'per_page' => $confessions->perPage(),
                'total' => $confessions->total(),
            ],
        ]);
    }

    /**
     * Mes confessions envoyées
     */
    public function sent(Request $request): JsonResponse
    {
        $user = $request->user();

        $confessions = Confession::where('author_id', $user->id)
            ->with('recipient:id,first_name,last_name,username,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'confessions' => ConfessionResource::collection($confessions),
            'meta' => [
                'current_page' => $confessions->currentPage(),
                'last_page' => $confessions->lastPage(),
                'per_page' => $confessions->perPage(),
                'total' => $confessions->total(),
            ],
        ]);
    }

    /**
     * Détail d'une confession
     */
    public function show(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        // Vérifier l'accès
        if ($confession->is_private) {
            if ($confession->recipient_id !== $user?->id && $confession->author_id !== $user?->id) {
                return response()->json([
                    'message' => 'Accès non autorisé.',
                ], 403);
            }
        } else {
            // Confession publique : doit être approuvée
            if ($confession->status !== Confession::STATUS_APPROVED && $confession->author_id !== $user?->id) {
                return response()->json([
                    'message' => 'Confession non disponible.',
                ], 404);
            }
        }

        // Incrémenter les vues (sauf pour l'auteur)
        if ($confession->author_id !== $user?->id) {
            $confession->incrementViews();
        }

        $confession->load('author:id,first_name,last_name,username,avatar,is_premium,premium_expires_at');
        
        if ($user) {
            $confession->is_liked = $confession->isLikedBy($user);
        }

        return response()->json([
            'confession' => new ConfessionResource($confession),
        ]);
    }

    /**
     * Créer une confession
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validation personnalisée pour gérer les médias
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:5000', // Optionnel si média présent
            'type' => 'required|in:private,public',
            'is_identity_revealed' => 'boolean',
            'recipient_username' => 'nullable|string',
            'media_type' => 'nullable|in:none,image,video',
            'media' => 'nullable|file|max:102400', // 100MB max pour vidéos
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Valider qu'au moins content OU media est présent
        $hasContent = !empty($validated['content']);
        $hasMedia = $request->hasFile('media') && ($validated['media_type'] ?? 'none') !== 'none';

        if (!$hasContent && !$hasMedia) {
            return response()->json([
                'message' => 'Vous devez fournir un contenu texte ou un média.',
            ], 422);
        }

        $confessionData = [
            'author_id' => $user->id,
            'content' => $validated['content'] ?? '', // Vide si pas de contenu
            'type' => $validated['type'],
            'is_identity_revealed' => $validated['is_identity_revealed'] ?? false,
            'media_type' => $validated['media_type'] ?? 'none',
        ];

        // Gestion du média (image ou vidéo)
        if (($validated['media_type'] ?? 'none') !== 'none' && $request->hasFile('media')) {
            $media = $request->file('media');

            // Valider le type MIME selon le type
            if ($validated['media_type'] === 'image') {
                if (!in_array($media->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    return response()->json([
                        'message' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP).',
                    ], 422);
                }
            } elseif ($validated['media_type'] === 'video') {
                if (!in_array($media->getMimeType(), ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'])) {
                    return response()->json([
                        'message' => 'Le fichier doit être une vidéo (MP4, MOV, AVI, WebM).',
                    ], 422);
                }
            }

            // Sauvegarder le média
            $path = $media->store('confessions/' . $user->id, 'public');
            $confessionData['media_url'] = $path;

            // Gérer le thumbnail pour les vidéos
            if ($validated['media_type'] === 'video' && $request->hasFile('thumbnail')) {
                \Log::info('📸 [CONFESSION] Thumbnail vidéo reçu');
                $thumbnail = $request->file('thumbnail');

                // Valider que c'est bien une image
                if (in_array($thumbnail->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
                    $thumbnailPath = $thumbnail->store('confessions/' . $user->id . '/thumbnails', 'public');
                    $confessionData['thumbnail_url'] = $thumbnailPath;
                    \Log::info('✅ [CONFESSION] Thumbnail sauvegardé: ' . $thumbnailPath);
                } else {
                    \Log::warning('⚠️ [CONFESSION] Format de thumbnail non supporté: ' . $thumbnail->getMimeType());
                }
            }
        }

        // Si confession privée, vérifier le destinataire
        if ($validated['type'] === Confession::TYPE_PRIVATE) {
            if (empty($validated['recipient_username'])) {
                return response()->json([
                    'message' => 'Un destinataire est requis pour une confession privée.',
                ], 422);
            }

            $recipient = User::where('username', $validated['recipient_username'])->first();

            if (!$recipient) {
                return response()->json([
                    'message' => 'Destinataire non trouvé.',
                ], 404);
            }

            if ($recipient->id === $user->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez pas vous envoyer une confession.',
                ], 422);
            }

            if ($user->isBlockedBy($recipient) || $user->hasBlocked($recipient)) {
                return response()->json([
                    'message' => 'Impossible d\'envoyer une confession à cet utilisateur.',
                ], 422);
            }

            $confessionData['recipient_id'] = $recipient->id;
            $confessionData['status'] = Confession::STATUS_APPROVED; // Privées = auto-approuvées
        } else {
            // Confessions publiques = auto-approuvées
            $confessionData['status'] = Confession::STATUS_APPROVED;
        }

        $confession = Confession::create($confessionData);

        // Envoyer notification si confession privée
        if ($confession->is_private && $confession->recipient) {
            $this->notificationService->sendNewConfessionNotification($confession);
        }

        return response()->json([
            'message' => $confession->is_public
                ? 'Confession publique publiée avec succès.'
                : 'Confession envoyée avec succès.',
            'confession' => new ConfessionResource($confession),
        ], 201);
    }

    /**
     * Supprimer une confession
     */
    public function destroy(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        // Seul l'auteur ou le destinataire peut supprimer
        if ($confession->author_id !== $user->id && $confession->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Supprimer le média du stockage si présent
        if ($confession->media_url) {
            Storage::disk('public')->delete($confession->media_url);
        }

        // Supprimer le thumbnail du stockage si présent
        if ($confession->thumbnail_url) {
            Storage::disk('public')->delete($confession->thumbnail_url);
        }

        $confession->delete();

        return response()->json([
            'message' => 'Confession supprimée avec succès.',
        ]);
    }

    /**
     * Liker une confession publique
     */
    public function like(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        if (!$confession->is_public || !$confession->is_approved) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $liked = $confession->like($user);

        return response()->json([
            'message' => $liked ? 'Confession likée.' : 'Vous avez déjà liké cette confession.',
            'likes_count' => $confession->fresh()->likes_count,
            'is_liked' => true,
        ]);
    }

    /**
     * Unliker une confession
     */
    public function unlike(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        $unliked = $confession->unlike($user);

        return response()->json([
            'message' => $unliked ? 'Like retiré.' : 'Vous n\'avez pas liké cette confession.',
            'likes_count' => $confession->fresh()->likes_count,
            'is_liked' => false,
        ]);
    }

    /**
     * Signaler une confession
     */
    public function report(ReportConfessionRequest $request, Confession $confession): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($confession->isReportedBy($user)) {
            return response()->json([
                'message' => 'Vous avez déjà signalé cette confession.',
            ], 422);
        }

        $confession->report($user, $validated['reason'], $validated['description'] ?? null);

        return response()->json([
            'message' => 'Signalement envoyé. Merci pour votre vigilance.',
        ], 201);
    }

    /**
     * Révéler l'identité de l'auteur (pour confession privée + premium)
     */
    public function reveal(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        if ($confession->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        if ($confession->is_identity_revealed) {
            return response()->json([
                'message' => 'L\'identité a déjà été révélée.',
                'author' => $confession->author_info,
            ]);
        }

        // Vérifier abonnement premium
        // Note: Pour les confessions, on utilise un système similaire aux messages
        // L'utilisateur doit avoir un abonnement actif

        // TODO: Implémenter la logique premium pour les confessions

        return response()->json([
            'message' => 'Un abonnement premium est requis.',
            'requires_premium' => true,
        ], 402);
    }

    /**
     * Statistiques des confessions
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'received_count' => Confession::forRecipient($user->id)->count(),
            'sent_count' => Confession::where('author_id', $user->id)->count(),
            'public_approved_count' => Confession::where('author_id', $user->id)
                ->publicApproved()
                ->count(),
            'pending_count' => Confession::where('author_id', $user->id)
                ->pending()
                ->count(),
        ]);
    }

    /**
     * Récupérer les commentaires d'une confession
     */
    public function getComments(Request $request, Confession $confession): JsonResponse
    {
        // Vérifier l'accès à la confession
        if ($confession->is_private) {
            $user = $request->user();
            if ($confession->recipient_id !== $user?->id && $confession->author_id !== $user?->id) {
                return response()->json([
                    'message' => 'Accès non autorisé.',
                ], 403);
            }
        } else {
            // Confession publique : doit être approuvée
            if ($confession->status !== Confession::STATUS_APPROVED) {
                return response()->json([
                    'message' => 'Confession non disponible.',
                ], 404);
            }
        }

        $currentUserId = $request->user()?->id;

        // Récupérer seulement les commentaires de premier niveau (pas les réponses)
        $comments = $confession->comments()
            ->whereNull('parent_id')
            ->with([
                'author:id,first_name,username,avatar',
                'replies.author:id,first_name,username,avatar',
                'replies' => function ($query) {
                    $query->withCount('likedBy');
                }
            ])
            ->withCount(['likedBy', 'replies'])
            ->orderBy('created_at', 'desc') // Les plus récents en premier
            ->get()
            ->map(function ($comment) use ($currentUserId, $request) {
                // Vérifier si l'utilisateur connecté a un Premium Pass actif
                $viewer = $request->user();
                $hasPremiumPass = $viewer && $viewer->is_premium && $viewer->premium_expires_at && $viewer->premium_expires_at->isFuture();
                $isOwner = $comment->author_id === $currentUserId;
                $shouldRevealIdentity = $isOwner || $hasPremiumPass;

                $commentData = [
                    'id' => $comment->id,
                    'content' => $comment->getDecryptedAttribute('content') ?? $comment->content,
                    'is_anonymous' => $comment->is_anonymous,
                    'media_type' => $comment->media_type ?? 'none',
                    'media_url' => $comment->media_url ? asset('storage/' . $comment->media_url) : null,
                    'author' => ($comment->is_anonymous && !$shouldRevealIdentity) ? [
                        'name' => 'Anonyme',
                        'initial' => '?',
                        'avatar_url' => null,
                    ] : [
                        'id' => $comment->author->id,
                        'name' => $comment->author->first_name,
                        'username' => $comment->author->username,
                        'initial' => $comment->author->initial,
                        'avatar_url' => $comment->author->avatar_url,
                    ],
                    'created_at' => $comment->created_at,
                    'is_mine' => $comment->author_id === $currentUserId,
                    'likes_count' => $comment->liked_by_count ?? 0,
                    'is_liked' => $request->user() ? $comment->isLikedBy($request->user()) : false,
                    'replies_count' => $comment->replies_count ?? 0,
                    'replies' => $comment->replies->map(function ($reply) use ($currentUserId, $request, $hasPremiumPass) {
                        $isReplyOwner = $reply->author_id === $currentUserId;
                        $shouldRevealReplyIdentity = $isReplyOwner || $hasPremiumPass;

                        return [
                            'id' => $reply->id,
                            'content' => $reply->getDecryptedAttribute('content') ?? $reply->content,
                            'is_anonymous' => $reply->is_anonymous,
                            'media_type' => $reply->media_type ?? 'none',
                            'media_url' => $reply->media_url ? asset('storage/' . $reply->media_url) : null,
                            'author' => ($reply->is_anonymous && !$shouldRevealReplyIdentity) ? [
                                'name' => 'Anonyme',
                                'initial' => '?',
                                'avatar_url' => null,
                            ] : [
                                'id' => $reply->author->id,
                                'name' => $reply->author->first_name,
                                'username' => $reply->author->username,
                                'initial' => $reply->author->initial,
                                'avatar_url' => $reply->author->avatar_url,
                            ],
                            'created_at' => $reply->created_at,
                            'is_mine' => $reply->author_id === $currentUserId,
                            'likes_count' => $reply->liked_by_count ?? 0,
                            'is_liked' => $request->user() ? $reply->isLikedBy($request->user()) : false,
                        ];
                    }),
                ];

                return $commentData;
            });

        return response()->json([
            'comments' => $comments,
            'total' => $comments->count(),
        ]);
    }

    /**
     * Ajouter un commentaire
     */
    public function addComment(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        // Vérifier l'accès à la confession
        if ($confession->is_private) {
            if ($confession->recipient_id !== $user->id && $confession->author_id !== $user->id) {
                return response()->json([
                    'message' => 'Accès non autorisé.',
                ], 403);
            }
        } else {
            // Confession publique : doit être approuvée
            if ($confession->status !== Confession::STATUS_APPROVED) {
                return response()->json([
                    'message' => 'Confession non disponible.',
                ], 404);
            }
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:1000',
            'is_anonymous' => 'nullable', // Accepter n'importe quoi, on va le convertir après
            'parent_id' => 'nullable|exists:confession_comments,id', // Pour les réponses
            'media_type' => 'nullable|in:none,audio,image',
            'media' => 'nullable|file|max:20480', // 20MB max
            'voice_type' => 'nullable|in:normal,robot,alien,mystery,chipmunk', // Type d'effet vocal
        ]);

        // Convertir is_anonymous en booléen (accepte: true, false, 1, 0, "1", "0", "true", "false")
        $isAnonymousValue = $request->input('is_anonymous', false);
        if (is_string($isAnonymousValue)) {
            $isAnonymousValue = filter_var($isAnonymousValue, FILTER_VALIDATE_BOOLEAN);
        } else {
            $isAnonymousValue = (bool) $isAnonymousValue;
        }
        $validated['is_anonymous'] = $isAnonymousValue;

        // Valider qu'au moins content OU media est présent
        if (empty($validated['content']) && !$request->hasFile('media')) {
            return response()->json([
                'message' => 'Vous devez fournir un contenu texte ou un média.',
            ], 422);
        }

        $commentData = [
            'author_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'] ?? '',
            'is_anonymous' => $validated['is_anonymous'] ?? false,
            'media_type' => $validated['media_type'] ?? 'none',
            'voice_type' => $validated['voice_type'] ?? 'normal',
        ];

        // Gestion du média (audio ou image)
        if ($request->hasFile('media')) {
            $media = $request->file('media');
            $mediaType = $validated['media_type'] ?? 'none';

            // Déterminer le dossier selon le type
            $folder = match($mediaType) {
                'audio' => 'comments/audio',
                'image' => 'comments/images',
                default => 'comments',
            };

            // Stocker le fichier dans storage/app/public/comments/{type}/{userId}
            $path = $media->store($folder . '/' . $user->id, 'public');
            $commentData['media_url'] = $path;
        }

        $comment = $confession->comments()->create($commentData);

        \Log::info('💬 Comment created', [
            'comment_id' => $comment->id,
            'is_anonymous' => $comment->is_anonymous,
            'media_type' => $comment->media_type,
            'voice_type' => $comment->voice_type,
            'media_url' => $comment->media_url,
        ]);

        // Si c'est un commentaire audio avec un effet vocal (et pas normal), traiter de manière synchrone
        if ($comment->media_type === 'audio' && $comment->voice_type !== 'normal' && $comment->media_url) {
            \Log::info('🎤 Processing voice effect synchronously', [
                'comment_id' => $comment->id,
                'voice_type' => $comment->voice_type,
            ]);

            // Traiter de manière synchrone pour que l'URL mise à jour soit disponible immédiatement
            \App\Jobs\ProcessVoiceEffect::dispatchSync(
                $comment->id,
                $comment->media_url,
                $comment->voice_type
            );

            // Recharger le commentaire pour obtenir le media_url mis à jour
            $comment->refresh();
        } else {
            \Log::info('⏭️ Skipping voice effect processing', [
                'reason' => $comment->media_type !== 'audio' ? 'not audio' : ($comment->voice_type === 'normal' ? 'voice type is normal' : 'no media url'),
            ]);
        }

        $comment->load('author:id,first_name,username,avatar');
        $comment->loadCount('likedBy');

        // Envoyer une notification FCM
        try {
            $this->notificationService->sendConfessionCommentNotification($confession, $comment);
        } catch (\Exception $e) {
            \Log::warning('Confession comment notification failed: ' . $e->getMessage());
        }

        // Vérifier si l'utilisateur connecté a un Premium Pass actif
        $viewer = $request->user();
        $hasPremiumPass = $viewer && $viewer->is_premium && $viewer->premium_expires_at && $viewer->premium_expires_at->isFuture();
        $isOwner = true; // C'est le commentaire qu'on vient de créer
        $shouldRevealIdentity = $isOwner || $hasPremiumPass;

        return response()->json([
            'message' => 'Commentaire ajouté avec succès.',
            'comment' => [
                'id' => $comment->id,
                'content' => $comment->getDecryptedAttribute('content') ?? $comment->content,
                'is_anonymous' => $comment->is_anonymous,
                'media_type' => $comment->media_type ?? 'none',
                'media_url' => $comment->media_url ? asset('storage/' . $comment->media_url) : null,
                'author' => ($comment->is_anonymous && !$shouldRevealIdentity) ? [
                    'name' => 'Anonyme',
                    'initial' => '?',
                    'avatar_url' => null,
                ] : [
                    'id' => $comment->author->id,
                    'name' => $comment->author->first_name,
                    'username' => $comment->author->username,
                    'initial' => $comment->author->initial,
                    'avatar_url' => $comment->author->avatar_url,
                ],
                'created_at' => $comment->created_at,
                'is_mine' => true,
                'likes_count' => $comment->liked_by_count ?? 0,
                'is_liked' => false,
            ],
        ], 201);
    }

    /**
     * Supprimer un commentaire
     */
    public function deleteComment(Request $request, Confession $confession, ConfessionComment $comment): JsonResponse
    {
        $user = $request->user();

        // Vérifier que le commentaire appartient bien à cette confession
        if ($comment->confession_id !== $confession->id) {
            return response()->json([
                'message' => 'Commentaire non trouvé.',
            ], 404);
        }

        // Seul l'auteur du commentaire peut le supprimer
        if ($comment->author_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'message' => 'Commentaire supprimé avec succès.',
        ]);
    }

    /**
     * Liker un commentaire
     */
    public function likeComment(Request $request, Confession $confession, ConfessionComment $comment): JsonResponse
    {
        $user = $request->user();

        // Vérifier que le commentaire appartient bien à cette confession
        if ($comment->confession_id !== $confession->id) {
            return response()->json([
                'message' => 'Commentaire non trouvé.',
            ], 404);
        }

        $liked = $comment->like($user);

        return response()->json([
            'message' => $liked ? 'Commentaire liké.' : 'Vous avez déjà liké ce commentaire.',
            'likes_count' => $comment->fresh()->likes_count,
            'is_liked' => true,
        ]);
    }

    /**
     * Unliker un commentaire
     */
    public function unlikeComment(Request $request, Confession $confession, ConfessionComment $comment): JsonResponse
    {
        $user = $request->user();

        // Vérifier que le commentaire appartient bien à cette confession
        if ($comment->confession_id !== $confession->id) {
            return response()->json([
                'message' => 'Commentaire non trouvé.',
            ], 404);
        }

        $unliked = $comment->unlike($user);

        return response()->json([
            'message' => $unliked ? 'Like retiré.' : 'Vous n\'avez pas liké ce commentaire.',
            'likes_count' => $comment->fresh()->likes_count,
            'is_liked' => false,
        ]);
    }

    /**
     * Toggle favorite status (Add/Remove from bookmarks)
     */
    public function toggleFavorite(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        if (!$confession->is_public || !$confession->is_approved) {
            return response()->json([
                'message' => 'Action non autorisée.',
            ], 403);
        }

        $isFavorited = $confession->favoritedBy()->where('user_id', $user->id)->exists();

        if ($isFavorited) {
            $confession->favoritedBy()->detach($user->id);
            $message = 'Confession retirée des favoris.';
            $is_favorited = false;
        } else {
            $confession->favoritedBy()->attach($user->id);
            $message = 'Confession ajoutée aux favoris.';
            $is_favorited = true;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_favorited' => $is_favorited,
        ]);
    }

    /**
     * Get user's favorite confessions
     */
    public function favorites(Request $request): JsonResponse
    {
        $user = $request->user();

        $confessions = Confession::whereHas('favoritedBy', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->withTrashed() // Inclure les confessions supprimées
            ->publicApproved()
            ->with('author:id,first_name,last_name,username,avatar,is_premium,premium_expires_at')
            ->withCount(['comments'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Ajouter le flag "liked" pour l'utilisateur connecté
        $confessions->getCollection()->transform(function ($confession) use ($user) {
            $confession->is_liked = $confession->isLikedBy($user);
            $confession->is_favorited = true; // Toutes sont favorisées ici
            $confession->is_deleted = $confession->trashed(); // Marquer si supprimée
            return $confession;
        });

        return response()->json([
            'confessions' => ConfessionResource::collection($confessions),
            'meta' => [
                'current_page' => $confessions->currentPage(),
                'last_page' => $confessions->lastPage(),
                'per_page' => $confessions->perPage(),
                'total' => $confessions->total(),
            ],
        ]);
    }

    /**
     * Update a confession
     */
    public function update(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        // Seul l'auteur peut modifier
        if ($confession->author_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Validation
        $validator = Validator::make($request->all(), [
            'content' => 'nullable|string|max:5000',
            'media_type' => 'nullable|in:none,image,video',
            'media' => 'nullable|file|max:102400', // 100MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Données invalides.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Mettre à jour le contenu
        if (isset($validated['content'])) {
            $confession->content = $validated['content'];
        }

        // Gérer le nouveau média
        if (($validated['media_type'] ?? 'none') !== 'none' && $request->hasFile('media')) {
            // Supprimer l'ancien média
            if ($confession->media_url) {
                Storage::disk('public')->delete($confession->media_url);
            }

            $media = $request->file('media');

            // Valider le type MIME
            if ($validated['media_type'] === 'image') {
                if (!in_array($media->getMimeType(), ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    return response()->json([
                        'message' => 'Le fichier doit être une image (JPEG, PNG, GIF, WebP).',
                    ], 422);
                }
            } elseif ($validated['media_type'] === 'video') {
                if (!in_array($media->getMimeType(), ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'])) {
                    return response()->json([
                        'message' => 'Le fichier doit être une vidéo (MP4, MOV, AVI, WebM).',
                    ], 422);
                }
            }

            $path = $media->store('confessions/' . $user->id, 'public');
            $confession->media_url = $path;
            $confession->media_type = $validated['media_type'];
        }

        $confession->save();

        return response()->json([
            'success' => true,
            'message' => 'Confession mise à jour avec succès.',
            'confession' => new ConfessionResource($confession),
        ]);
    }

    /**
     * Reveal identity for public anonymous confession
     */
    public function revealIdentity(Request $request, Confession $confession): JsonResponse
    {
        $user = $request->user();

        if (!$confession->is_public) {
            return response()->json([
                'message' => 'Cette fonctionnalité est uniquement pour les confessions publiques.',
            ], 403);
        }

        if ($confession->is_identity_revealed) {
            // Identité déjà publique
            $author = $confession->author;
            return response()->json([
                'success' => true,
                'author' => [
                    'id' => $author->id,
                    'name' => $author->first_name . ' ' . $author->last_name,
                    'username' => $author->username,
                    'avatar_url' => $author->avatar_url,
                ],
            ]);
        }

        // Vérifier si l'utilisateur a déjà révélé cette identité
        $alreadyRevealed = $confession->identityRevealedBy()->where('user_id', $user->id)->exists();

        if (!$alreadyRevealed) {
            $confession->identityRevealedBy()->attach($user->id);
        }

        // Retourner l'info de l'auteur
        $author = $confession->author;
        if (!$author) {
            return response()->json([
                'success' => false,
                'message' => 'Auteur non trouvé.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'author' => [
                'id' => $author->id,
                'name' => $author->first_name . ' ' . $author->last_name,
                'username' => $author->username,
                'avatar_url' => $author->avatar_url,
            ],
        ]);
    }
}
