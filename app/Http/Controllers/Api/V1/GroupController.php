<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\GroupReport;
use App\Models\User;
use App\Events\GroupMessageSent;
use App\Services\AudioProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    /**
     * Liste des groupes de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $groups = Group::whereHas('activeMembers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['lastMessage', 'category'])
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Ajouter des infos supplémentaires
        $groups->getCollection()->transform(function ($group) use ($user) {
            $group->unread_count = $group->unreadCountFor($user);
            $group->is_creator = $group->isCreator($user);
            $group->is_admin = $group->isAdmin($user);
            return $group;
        });

        return response()->json([
            'groups' => $groups->items(),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * Créer un nouveau groupe
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:group_categories,id',
            'is_public' => 'boolean',
            'is_discoverable' => 'boolean',
            'max_members' => 'nullable|integer|min:2',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Gérer l'upload de l'avatar si présent
            $avatarUrl = null;
            if ($request->hasFile('avatar')) {
                $avatarFile = $request->file('avatar');
                $avatarPath = $avatarFile->store('groups/avatars', 'public');
                $avatarUrl = asset('storage/' . $avatarPath);
            }

            // Créer le groupe
            $group = Group::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'creator_id' => $user->id,
                'invite_code' => Group::generateInviteCode(),
                'is_public' => $validated['is_public'] ?? false,
                'is_discoverable' => $validated['is_discoverable'] ?? true,
                'max_members' => $validated['max_members'] ?? Group::MAX_MEMBERS_DEFAULT,
                'members_count' => 1,
                'avatar_url' => $avatarUrl,
            ]);

            // Ajouter le créateur comme admin
            $member = $group->members()->create([
                'user_id' => $user->id,
                'role' => GroupMember::ROLE_ADMIN,
                'joined_at' => now(),
            ]);

            // Message système de bienvenue anonyme
            GroupMessage::createSystemMessage($group, "Groupe créé par Anonyme");

            DB::commit();

            // Ajouter les flags is_creator et is_admin
            $group->is_creator = true; // Le créateur vient de créer le groupe
            $group->is_admin = true;   // Le créateur est aussi admin
            $group->unread_count = 0;  // Pas de messages non lus au départ

            // Recharger le groupe pour avoir tous les champs
            $group->refresh();

            return response()->json([
                'message' => 'Groupe créé avec succès.',
                'group' => $group,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du groupe.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Détails d'un groupe
     */
    public function show(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $group->load([
            'lastMessage',
        ]);

        $group->unread_count = $group->unreadCountFor($user);
        $group->is_creator = $group->isCreator($user);
        $group->is_admin = $group->isAdmin($user);

        return response()->json([
            'group' => $group,
        ]);
    }

    /**
     * Mettre à jour un groupe
     */
    public function update(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Seul le créateur ou un admin peut modifier
        if (!$group->isCreator($user) && !$group->isAdmin($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:group_categories,id',
            'is_public' => 'sometimes|boolean',
            'is_discoverable' => 'sometimes|boolean',
            'max_members' => 'sometimes|integer|min:2',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:5120', // Max 5MB
        ]);

        // Vérifier si l'utilisateur essaie de renommer le groupe
        // Seul le créateur peut renommer le groupe
        if (isset($validated['name']) && !$group->isCreator($user)) {
            return response()->json([
                'message' => 'Seul le créateur peut renommer le groupe.',
            ], 403);
        }

        // Gérer l'upload de l'avatar si fourni
        if ($request->hasFile('avatar')) {
            // Supprimer l'ancien avatar s'il existe
            if ($group->avatar_url) {
                // Extraire le chemin du storage depuis l'URL complète ou relative
                $oldPath = str_replace([asset('storage/'), '/storage/'], '', $group->avatar_url);
                Storage::disk('public')->delete($oldPath);
            }

            // Enregistrer le nouvel avatar (même dossier que lors de la création)
            $avatarFile = $request->file('avatar');
            $avatarPath = $avatarFile->store('groups/avatars', 'public');
            $validated['avatar_url'] = asset('storage/' . $avatarPath);
        }

        $group->update($validated);

        // Recharger le groupe avec toutes les relations et attributs calculés
        $group->refresh();
        $group->load([
            'category',
            'lastMessage',
        ]);

        // Ajouter les attributs calculés pour l'utilisateur connecté
        $group->unread_count = $group->unreadCountFor($user);
        $group->is_creator = $group->isCreator($user);
        $group->is_admin = $group->isAdmin($user);

        return response()->json([
            'message' => 'Groupe mis à jour avec succès.',
            'group' => $group,
        ]);
    }

    /**
     * Supprimer un groupe (seul le créateur)
     */
    public function destroy(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->isCreator($user)) {
            return response()->json([
                'message' => 'Seul le créateur peut supprimer le groupe.',
            ], 403);
        }

        $group->delete();

        return response()->json([
            'message' => 'Groupe supprimé avec succès.',
        ]);
    }

    /**
     * Découvrir les groupes publics
     */
    public function discover(Request $request): JsonResponse
    {
        // Récupérer les groupes publics OU les groupes privés découvrables
        $query = Group::where(function($q) {
                $q->where('is_public', true)
                  ->orWhere(function($subQuery) {
                      $subQuery->where('is_public', false)
                               ->where('is_discoverable', true);
                  });
            })
            ->with(['lastMessage', 'category']);

        // Filtrage par recherche
        if ($request->has('search')) {
            $query->search($request->get('search'));
        }

        // Filtrage par catégorie
        if ($request->has('category_id') && $request->get('category_id') !== null) {
            $query->byCategory($request->get('category_id'));
        }

        // Tri
        $sortBy = $request->get('sort_by', 'recent');
        if ($sortBy === 'recent') {
            $query->withRecentActivity();
        } elseif ($sortBy === 'members') {
            $query->orderBy('members_count', 'desc');
        } elseif ($sortBy === 'name') {
            $query->orderBy('name', 'asc');
        }

        $groups = $query->paginate($request->get('per_page', 20));

        // Ajouter des infos supplémentaires pour chaque groupe
        $user = $request->user();
        $groups->getCollection()->transform(function ($group) use ($user) {
            $group->is_member = $group->hasMember($user);
            $group->can_join = !$group->is_member && $group->canAcceptMoreMembers();
            return $group;
        });

        return response()->json([
            'groups' => $groups->items(),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * Rejoindre un groupe via code d'invitation ou ID (pour les groupes publics)
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => 'nullable|string|size:8',
            'group_id' => 'nullable|integer|exists:groups,id',
        ]);

        // On doit avoir soit un code d'invitation, soit un ID de groupe
        if (empty($validated['invite_code']) && empty($validated['group_id'])) {
            return response()->json([
                'message' => 'Vous devez fournir soit un code d\'invitation, soit un ID de groupe.',
            ], 422);
        }

        $user = $request->user();

        // Récupérer le groupe selon le paramètre fourni
        if (!empty($validated['invite_code'])) {
            $group = Group::where('invite_code', $validated['invite_code'])->firstOrFail();
        } else {
            $group = Group::findOrFail($validated['group_id']);

            // Pour rejoindre par ID, le groupe doit être public
            if (!$group->is_public) {
                return response()->json([
                    'message' => 'Ce groupe est privé. Vous devez utiliser un code d\'invitation.',
                ], 403);
            }
        }

        // Vérifier si déjà membre
        if ($group->hasMember($user)) {
            return response()->json([
                'message' => 'Vous êtes déjà membre de ce groupe.',
            ], 422);
        }

        // Vérifier la limite de membres
        if (!$group->canAcceptMoreMembers()) {
            return response()->json([
                'message' => 'Le groupe a atteint sa limite de membres.',
            ], 422);
        }

        // Ajouter le membre
        $member = $group->addMember($user);

        if (!$member) {
            return response()->json([
                'message' => 'Impossible de rejoindre le groupe.',
            ], 500);
        }

        // Recharger le groupe avec les relations et informations nécessaires
        $group->load(['lastMessage']);
        $group->unread_count = $group->unreadCountFor($user);
        $group->is_creator = $group->isCreator($user);
        $group->is_admin = $group->isAdmin($user);
        $group->is_member = true; // L'utilisateur vient de rejoindre le groupe
        $group->can_join = false; // L'utilisateur est déjà membre, il ne peut plus rejoindre

        return response()->json([
            'message' => 'Vous avez rejoint le groupe avec succès.',
            'group' => $group,
        ], 201);
    }

    /**
     * Liste des messages d'un groupe
     * Support de la pagination avec before pour charger les anciens messages
     */
    public function messages(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $canSeeIdentity = $user->has_active_premium ?? false;
        $perPage = $request->get('per_page', 10); // Par défaut 10 messages
        $before = $request->get('before'); // Timestamp ou message_id pour charger les anciens

        // Construire la requête de base - Toujours charger le sender pour avoir l'avatar
        $query = $group->messages()->with('sender');

        // Si 'before' est fourni, charger les messages avant ce timestamp/id
        if ($before) {
            // Vérifier si c'est un ID ou un timestamp
            if (is_numeric($before) && $before < 1000000000000) {
                // C'est un message_id
                $query->where('id', '<', $before);
            } else {
                // C'est un timestamp
                $query->where('created_at', '<', $before);
            }
        }

        // Toujours trier par DESC pour avoir les plus récents en premier
        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        // Limiter les résultats
        $messages = $query->limit($perPage)->get();

        // Inverser pour avoir l'ordre chronologique (ancien → récent) pour l'affichage
        $messages = $messages->reverse()->values();

        // Ajouter l'info si c'est le message de l'utilisateur et les données du sender
        $messages->transform(function ($message) use ($user, $canSeeIdentity) {
            $message->is_own = $message->sender_id === $user->id;

            // L'avatar est toujours visible, mais le nom dépend du statut Premium
            if ($message->sender) {
                // Avatar toujours visible
                $message->sender_avatar_url = $message->sender->avatar_url;
                $message->sender_initial = $message->sender->initial;

                // Nom visible seulement si Premium
                if ($canSeeIdentity) {
                    $message->sender_first_name = $message->sender->first_name;
                    $message->sender_last_name = $message->sender->last_name;
                    $message->sender_username = $message->sender->username;
                    $message->sender_is_premium = $message->sender->is_premium ?? false;
                    $message->sender_is_verified = $message->sender->is_verified ?? false;

                    // Remplacer la relation sender par un objet simple
                    $message->setRelation('sender', [
                        'id' => $message->sender->id,
                        'first_name' => $message->sender->first_name,
                        'last_name' => $message->sender->last_name,
                        'username' => $message->sender->username,
                        'avatar_url' => $message->sender->avatar_url,
                        'is_premium' => $message->sender->is_premium ?? false,
                        'is_verified' => $message->sender->is_verified ?? false,
                    ]);
                } else {
                    // Pas Premium : Anonyme pour le nom mais avatar visible
                    $message->sender_name = 'Anonyme';
                    // Toujours envoyer l'avatar même si anonyme
                    $message->setRelation('sender', [
                        'id' => $message->sender->id,
                        'first_name' => 'Anonyme',
                        'last_name' => '',
                        'username' => 'anonyme',
                        'avatar_url' => $message->sender->avatar_url, // Avatar toujours visible
                        'is_premium' => false,
                        'is_verified' => false,
                    ]);
                }
            }

            return $message;
        });

        // Déterminer s'il y a plus de messages à charger
        $hasMore = false;
        if ($messages->count() > 0) {
            $oldestMessageId = $messages->first()->id;
            $hasMore = $group->messages()
                ->where('id', '<', $oldestMessageId)
                ->exists();
        }

        return response()->json([
            'messages' => $messages,
            'meta' => [
                'count' => $messages->count(),
                'has_more' => $hasMore,
                'oldest_message_id' => $messages->count() > 0 ? $messages->first()->id : null,
                'oldest_message_timestamp' => $messages->count() > 0 ? $messages->first()->created_at->toIso8601String() : null,
            ],
        ]);
    }

    /**
     * Envoyer un message dans un groupe
     */
    public function sendMessage(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:5000',
            'type' => 'nullable|string|in:text,image,audio,video,gift',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp3,wav,aac,m4a,mp4,mov|max:10240',
            'voice_type' => 'nullable|string|in:normal,robot,alien,mystery,chipmunk',
            'reply_to_message_id' => 'nullable|exists:group_messages,id',
            'metadata' => 'nullable|json',
        ]);

        $type = $validated['type'] ?? GroupMessage::TYPE_TEXT;
        $mediaUrl = null;
        $metadata = null;

        // Upload du fichier média si présent
        if ($request->hasFile('media')) {
            $file = $request->file('media');

            // Si c'est un audio avec voice_type, appliquer le traitement vocal
            if ($type === 'audio' && isset($validated['voice_type'])) {
                $voiceType = $validated['voice_type'];

                // Stocker le fichier
                $path = $file->store('group_messages', 'public');

                try {
                    // Utiliser le service AudioProcessingService pour appliquer l'effet
                    $audioService = app(AudioProcessingService::class);
                    $processedPath = $audioService->applyVoiceEffect($path, $voiceType);
                    $mediaUrl = asset('storage/' . $processedPath);
                } catch (\Exception $e) {
                    \Log::warning('Voice effect processing failed, using original audio: ' . $e->getMessage());
                    // Fallback: utiliser le fichier original
                    $mediaUrl = asset('storage/' . $path);
                }
            } else {
                // Pas d'audio ou pas de voice_type: stockage normal
                $path = $file->store('group_messages', 'public');
                $mediaUrl = asset('storage/' . $path);
            }
        }

        // Parser les metadata si présent
        if (isset($validated['metadata'])) {
            $metadata = json_decode($validated['metadata'], true);
        }

        // ==================== GESTION DES CADEAUX EN REPLY ====================
        // Si c'est un cadeau, vérifier le solde AVANT de créer le message
        if ($metadata && isset($metadata['gift']) && $metadata['gift'] === true && isset($validated['reply_to_message_id'])) {
            // Vérifier que l'envoyeur a assez de solde
            $giftPrice = $metadata['price'] ?? 0;
            if ($user->wallet_balance < $giftPrice) {
                return response()->json([
                    'message' => 'Solde insuffisant pour envoyer ce cadeau.',
                    'required' => $giftPrice,
                    'current_balance' => $user->wallet_balance,
                ], 402); // 402 Payment Required
            }
        }

        // Utiliser une transaction DB pour garantir la cohérence
        try {
            $message = \DB::transaction(function () use ($group, $user, $validated, $type, $mediaUrl, $metadata) {
                // Créer le message
                $message = GroupMessage::create([
                    'group_id' => $group->id,
                    'sender_id' => $user->id,
                    'content' => $validated['content'] ?? null,
                    'type' => $type,
                    'media_url' => $mediaUrl,
                    'metadata' => $metadata,
                    'reply_to_message_id' => $validated['reply_to_message_id'] ?? null,
                ]);

                // Si c'est un cadeau et qu'il y a un reply, gérer la transaction complète
                if ($metadata && isset($metadata['gift']) && $metadata['gift'] === true && $message->reply_to_message_id) {
                    // Récupérer le message original pour identifier le destinataire
                    $originalMessage = GroupMessage::find($message->reply_to_message_id);

                    if ($originalMessage && $originalMessage->sender_id) {
                        $recipient = User::find($originalMessage->sender_id);

                        if ($recipient) {
                            // Récupérer le cadeau depuis la metadata
                            $giftPrice = $metadata['price'] ?? 0;
                            $giftName = $metadata['name'] ?? 'Cadeau';
                            $giftIcon = $metadata['icon'] ?? '🎁';

                        // Calculer les montants
                        $amounts = \App\Models\GiftTransaction::calculateAmounts($giftPrice);

                        // DÉBITER l'envoyeur
                        $senderBalanceBefore = $user->wallet_balance;
                        $user->decrement('wallet_balance', $giftPrice);
                        $user->refresh();

                        // Créer une transaction de cadeau
                        $giftTransaction = \App\Models\GiftTransaction::create([
                            'gift_id' => null, // Pas de gift_id pour les cadeaux de groupe (custom)
                            'sender_id' => $user->id,
                            'recipient_id' => $recipient->id,
                            'conversation_id' => null,
                            'anonymous_message_id' => null,
                            'amount' => $amounts['amount'],
                            'platform_fee' => $amounts['platform_fee'],
                            'net_amount' => $amounts['net_amount'],
                            'status' => \App\Models\GiftTransaction::STATUS_COMPLETED,
                            'message' => "Cadeau de groupe: {$giftName}",
                            'is_anonymous' => false,
                        ]);

                        // Transaction de DÉBIT pour l'envoyeur
                        \App\Models\WalletTransaction::create([
                            'user_id' => $user->id,
                            'type' => \App\Models\WalletTransaction::TYPE_DEBIT,
                            'amount' => $giftPrice,
                            'balance_before' => $senderBalanceBefore,
                            'balance_after' => $user->wallet_balance,
                            'description' => "Cadeau envoyé dans un groupe: {$giftIcon} {$giftName} à {$recipient->username}",
                            'reference' => "GROUP_GIFT_SENT_{$message->id}",
                            'transactionable_type' => \App\Models\GiftTransaction::class,
                            'transactionable_id' => $giftTransaction->id,
                        ]);

                        // CRÉDITER le destinataire
                        $recipientBalanceBefore = $recipient->wallet_balance;
                        $recipient->increment('wallet_balance', $amounts['net_amount']);
                        $recipient->refresh();

                        // Transaction de CRÉDIT pour le destinataire
                        \App\Models\WalletTransaction::create([
                            'user_id' => $recipient->id,
                            'type' => \App\Models\WalletTransaction::TYPE_CREDIT,
                            'amount' => $amounts['net_amount'],
                            'balance_before' => $recipientBalanceBefore,
                            'balance_after' => $recipient->wallet_balance,
                            'description' => "Cadeau reçu dans un groupe: {$giftIcon} {$giftName} de {$user->username}",
                            'reference' => "GROUP_GIFT_RECEIVED_{$message->id}",
                            'transactionable_type' => \App\Models\GiftTransaction::class,
                            'transactionable_id' => $giftTransaction->id,
                        ]);

                            \Log::info('🎁 [GROUP GIFT] Transaction completed', [
                                'sender_id' => $user->id,
                                'sender_balance' => $user->wallet_balance,
                                'recipient_id' => $recipient->id,
                                'recipient_balance' => $recipient->wallet_balance,
                                'amount_sent' => $giftPrice,
                                'amount_received' => $amounts['net_amount'],
                                'platform_fee' => $amounts['platform_fee'],
                                'gift' => $giftName,
                                'group_id' => $group->id,
                            ]);
                        }
                    }
                }

                return $message;
            });
        } catch (\Exception $e) {
            // Log l'erreur et retourner une erreur au client (la transaction DB sera automatiquement rollback)
            \Log::error('🎁 [GROUP GIFT] Failed to process gift transaction: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur lors du traitement du cadeau.',
                'error' => $e->getMessage(),
            ], 500);
        }

        // Mettre à jour le groupe
        $group->updateAfterMessage();

        $message->is_own = true;

        // Avatar toujours visible, mais nom dépend du statut Premium
        $message->sender_avatar_url = $user->avatar_url; // Avatar toujours visible
        $message->sender_initial = $user->initial;

        $canSeeIdentity = $user->has_active_premium ?? false;
        if ($canSeeIdentity) {
            // Premium : Envoyer le vrai nom + avatar
            $message->sender_first_name = $user->first_name;
            $message->sender_last_name = $user->last_name;
            $message->sender_username = $user->username;
            $message->sender_is_premium = $user->is_premium ?? false;
            $message->sender_is_verified = $user->is_verified ?? false;
        } else {
            // Pas Premium : Anonyme pour le nom, mais avatar visible
            $message->sender_name = 'Anonyme';
        }

        // Diffuser l'événement en temps réel
        try {
            // Charger la relation sender pour le broadcast
            $message->load('sender');
            broadcast(new GroupMessageSent($message))->toOthers();
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'envoi du message
            \Log::warning('Broadcasting failed for group message: ' . $e->getMessage());
        }

        return response()->json([
            'message' => $message,
        ], 201);
    }

    /**
     * Supprimer un message de groupe
     */
    public function deleteMessage(Request $request, Group $group, GroupMessage $message): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est membre du groupe
        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Vérifier que le message appartient bien à ce groupe
        if ($message->group_id !== $group->id) {
            return response()->json([
                'message' => 'Ce message n\'appartient pas à ce groupe.',
            ], 403);
        }

        // Seul l'expéditeur peut supprimer son propre message
        if ($message->sender_id !== $user->id) {
            return response()->json([
                'message' => 'Vous ne pouvez supprimer que vos propres messages.',
            ], 403);
        }

        // Les messages système ne peuvent pas être supprimés
        if ($message->type === GroupMessage::TYPE_SYSTEM) {
            return response()->json([
                'message' => 'Les messages système ne peuvent pas être supprimés.',
            ], 403);
        }

        // Soft delete du message
        $messageId = $message->id;
        $message->delete();

        // Broadcast l'événement de suppression via WebSocket
        try {
            broadcast(new \App\Events\GroupMessageDeleted($group->id, $messageId))->toOthers();
        } catch (\Exception $e) {
            \Log::warning('Broadcasting failed for message deletion: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Message supprimé avec succès.',
        ]);
    }

    /**
     * Marquer les messages comme lus
     */
    public function markAsRead(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $member = $group->activeMembers()
            ->where('user_id', $user->id)
            ->first();

        if ($member) {
            $member->updateLastRead();
        }

        return response()->json([
            'message' => 'Messages marqués comme lus.',
        ]);
    }

    /**
     * Liste des membres d'un groupe
     */
    public function members(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Avatar toujours visible, mais nom dépend du statut Premium
        $canSeeIdentity = $user->has_active_premium ?? false;

        $members = $group->activeMembers()
            ->with('user')
            ->get()
            ->map(function ($member) use ($user, $canSeeIdentity) {
                $memberData = [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'role' => $member->role,
                    'joined_at' => $member->joined_at?->toIso8601String(),
                    'is_muted' => $member->is_muted,
                    'is_self' => $member->user_id === $user->id,
                    'is_identity_revealed' => $canSeeIdentity,
                ];

                if ($member->user) {
                    // Avatar toujours visible
                    $memberData['avatar_url'] = $member->user->avatar_url;
                    $memberData['initial'] = $member->user->initial;

                    // Nom visible seulement si Premium
                    if ($canSeeIdentity) {
                        $memberData['first_name'] = $member->user->first_name;
                        $memberData['last_name'] = $member->user->last_name;
                        $memberData['username'] = $member->user->username;
                        $memberData['is_premium'] = $member->user->is_premium ?? false;
                        $memberData['is_verified'] = $member->user->is_verified ?? false;
                    } else {
                        // Pas Premium : Anonyme pour le nom, mais avatar visible
                        $memberData['display_name'] = 'Anonyme';
                    }
                }

                return $memberData;
            });

        return response()->json([
            'members' => $members,
            'total' => $members->count(),
        ]);
    }

    /**
     * Retirer un membre du groupe (créateur uniquement)
     */
    public function removeMember(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        $user = $request->user();

        // Vérifier les permissions - Seul le créateur peut retirer un membre
        if (!$group->isCreator($user)) {
            return response()->json([
                'message' => 'Seul le créateur peut retirer des membres.',
            ], 403);
        }

        // Ne pas retirer le créateur
        if ($member->user_id === $group->creator_id) {
            return response()->json([
                'message' => 'Le créateur ne peut pas être retiré.',
            ], 422);
        }

        // Récupérer le nom anonyme avant de supprimer
        $anonymousName = $member->anonymous_name;
        $member->delete();
        $group->decrement('members_count');

        // Message système avec nom anonyme
        GroupMessage::createSystemMessage($group, "{$anonymousName} a été retiré du groupe");

        return response()->json([
            'message' => 'Membre retiré avec succès.',
        ]);
    }

    /**
     * Changer le rôle d'un membre (créateur uniquement)
     */
    public function updateMemberRole(Request $request, Group $group, GroupMember $member): JsonResponse
    {
        $user = $request->user();

        // Seul le créateur peut changer les rôles
        if (!$group->isCreator($user)) {
            return response()->json([
                'message' => 'Seul le créateur peut modifier les rôles.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                GroupMember::ROLE_ADMIN,
                GroupMember::ROLE_MODERATOR,
                GroupMember::ROLE_MEMBER,
            ])],
        ]);

        // Ne pas changer le rôle du créateur
        if ($member->user_id === $group->creator_id) {
            return response()->json([
                'message' => 'Le rôle du créateur ne peut pas être modifié.',
            ], 422);
        }

        $member->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Rôle mis à jour avec succès.',
            'member' => $member->fresh(),
        ]);
    }

    /**
     * Quitter un groupe (membres uniquement, pas le créateur)
     */
    public function leave(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est membre
        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Vous n\'êtes pas membre de ce groupe.',
            ], 403);
        }

        // Le créateur ne peut pas quitter son propre groupe
        if ($group->isCreator($user)) {
            return response()->json([
                'message' => 'Le créateur ne peut pas quitter son propre groupe. Vous devez le supprimer.',
            ], 422);
        }

        // Récupérer le membre
        $member = $group->members()->where('user_id', $user->id)->first();

        if (!$member) {
            return response()->json([
                'message' => 'Membre introuvable.',
            ], 404);
        }

        // Récupérer le nom anonyme avant de supprimer
        $anonymousName = $member->anonymous_name;
        $member->delete();
        $group->decrement('members_count');

        // Message système avec nom anonyme
        GroupMessage::createSystemMessage($group, "{$anonymousName} a quitté le groupe");

        return response()->json([
            'message' => 'Vous avez quitté le groupe avec succès.',
        ]);
    }

    /**
     * Régénérer le code d'invitation (créateur/admin uniquement)
     */
    public function regenerateInviteCode(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        if (!$group->isCreator($user) && !$group->isAdmin($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $group->regenerateInviteCode();

        return response()->json([
            'message' => 'Code d\'invitation régénéré.',
            'invite_code' => $group->invite_code,
            'invite_link' => $group->invite_link,
        ]);
    }

    /**
     * Statistiques des groupes
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $userGroups = Group::whereHas('activeMembers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        });

        $createdGroups = Group::where('creator_id', $user->id);

        return response()->json([
            'total_groups' => $userGroups->count(),
            'created_groups' => $createdGroups->count(),
            'active_groups' => $userGroups->clone()
                ->where('last_message_at', '>=', now()->subDays(7))
                ->count(),
            'total_messages_sent' => GroupMessage::where('sender_id', $user->id)->count(),
        ]);
    }

    /**
     * Obtenir le nombre total de messages non lus dans tous les groupes
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Récupérer tous les groupes de l'utilisateur
        $groups = Group::whereHas('activeMembers', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        // Calculer le total des messages non lus
        $totalUnreadCount = $groups->sum(function ($group) use ($user) {
            return $group->unreadCountFor($user);
        });

        return response()->json([
            'total_unread_count' => $totalUnreadCount,
        ]);
    }

    /**
     * Signaler un groupe (membres uniquement, pas le créateur)
     */
    public function report(Request $request, Group $group): JsonResponse
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est membre du groupe
        if (!$group->hasMember($user)) {
            return response()->json([
                'message' => 'Vous devez être membre du groupe pour le signaler.',
            ], 403);
        }

        // Le créateur ne peut pas signaler son propre groupe
        if ($group->isCreator($user)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas signaler votre propre groupe.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in([
                GroupReport::REASON_SPAM,
                GroupReport::REASON_HARASSMENT,
                GroupReport::REASON_INAPPROPRIATE_CONTENT,
                GroupReport::REASON_HATE_SPEECH,
                GroupReport::REASON_VIOLENCE,
                GroupReport::REASON_OTHER,
            ])],
            'description' => 'nullable|string|max:1000',
        ]);

        // Vérifier si l'utilisateur a déjà signalé ce groupe récemment (dans les 24h)
        $existingReport = GroupReport::where('group_id', $group->id)
            ->where('reporter_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existingReport) {
            return response()->json([
                'message' => 'Vous avez déjà signalé ce groupe récemment. Veuillez attendre avant de soumettre un nouveau signalement.',
            ], 422);
        }

        // Créer le signalement
        $report = GroupReport::create([
            'group_id' => $group->id,
            'reporter_id' => $user->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => GroupReport::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Signalement envoyé avec succès. Notre équipe va examiner votre demande.',
            'report' => $report,
        ], 201);
    }
}
