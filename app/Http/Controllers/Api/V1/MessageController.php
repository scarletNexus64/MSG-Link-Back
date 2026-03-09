<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\SendMessageRequest;
use App\Http\Requests\Message\ReportMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\AnonymousMessage;
use App\Models\User;
use App\Models\PremiumSubscription;
use App\Models\WalletTransaction;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\Conversation;
use App\Models\ChatMessage;
use App\Events\MessageSent;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private \App\Services\Notifications\NexahService $nexahService
    ) {}

    /**
     * Liste des messages reçus
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = AnonymousMessage::forRecipient($user->id)
            ->with('sender:id,first_name,last_name,username,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'messages' => MessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Messages envoyés
     */
    public function sent(Request $request): JsonResponse
    {
        $user = $request->user();

        $messages = AnonymousMessage::fromSender($user->id)
            ->with('recipient:id,first_name,last_name,username,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'messages' => MessageResource::collection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Détail d'un message
     */
    public function show(Request $request, AnonymousMessage $message): JsonResponse
    {
        $user = $request->user();

        // Vérifier les autorisations
        if ($message->recipient_id !== $user->id && $message->sender_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Charger la relation sender pour éviter les requêtes N+1
        $message->load('sender:id,first_name,last_name,username,avatar');

        // Marquer comme lu si destinataire
        if ($message->recipient_id === $user->id) {
            $message->markAsRead();
        }

        return response()->json([
            'message' => new MessageResource($message),
        ]);
    }

    /**
     * Envoyer un message anonyme (avec support audio, image, gift)
     */
    public function send(SendMessageRequest $request, string $username): JsonResponse
    {
        $sender = $request->user();
        $recipient = User::where('username', $username)->firstOrFail();

        // Vérifications
        if ($sender->id === $recipient->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas vous envoyer un message.',
            ], 422);
        }

        if ($recipient->is_banned) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est plus disponible.',
            ], 422);
        }

        // Vérifier si bloqué
        if ($sender->isBlockedBy($recipient) || $sender->hasBlocked($recipient)) {
            return response()->json([
                'message' => 'Impossible d\'envoyer un message à cet utilisateur.',
            ], 422);
        }

        $validated = $request->validated();
        $originalMessage = null;
        $createConversation = false;

        // Vérifier le message original si c'est une réponse
        if (!empty($validated['reply_to_message_id'])) {
            $originalMessage = AnonymousMessage::find($validated['reply_to_message_id']);

            // Vérifier que le message original existe et que l'utilisateur actuel est le destinataire
            if (!$originalMessage || $originalMessage->recipient_id !== $sender->id) {
                return response()->json([
                    'message' => 'Vous ne pouvez répondre qu\'aux messages que vous avez reçus.',
                ], 422);
            }

            // Le destinataire de la réponse est l'expéditeur du message original
            $recipient = User::find($originalMessage->sender_id);
            $createConversation = true; // Créer conversation automatiquement
        }

        DB::beginTransaction();
        try {
            // Préparation des données du message
            $messageData = [
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'content' => $validated['content'] ?? '',
                'reply_to_message_id' => $validated['reply_to_message_id'] ?? null,
                'media_type' => $validated['media_type'] ?? 'none',
                'voice_type' => $validated['voice_type'] ?? 'normal',
            ];

            // Gestion du média (audio ou image)
            if ($request->hasFile('media')) {
                $media = $request->file('media');
                $mediaType = $validated['media_type'] ?? 'none';

                // Déterminer le dossier selon le type
                $folder = match($mediaType) {
                    'audio' => 'anonymous_messages/audio',
                    'image' => 'anonymous_messages/images',
                    default => 'anonymous_messages/media',
                };

                // Stocker le fichier
                $path = $media->store($folder, 'public');
                $messageData['media_url'] = $path;

                \Log::info('📎 Media uploaded', [
                    'type' => $mediaType,
                    'path' => $path,
                    'sender_id' => $sender->id,
                ]);
            }

            // Créer le message
            $message = AnonymousMessage::create($messageData);

            // Traitement vocal si c'est un audio avec effet (pas normal)
            if ($message->media_type === 'audio' && $message->voice_type !== 'normal' && $message->media_url) {
                \Log::info('🎤 Processing voice effect synchronously', [
                    'message_id' => $message->id,
                    'voice_type' => $message->voice_type,
                ]);

                // Traiter de manière synchrone avec le bon type de modèle
                \App\Jobs\ProcessVoiceEffect::dispatchSync(
                    $message->id,
                    $message->media_url,
                    $message->voice_type,
                    \App\Models\AnonymousMessage::class
                );

                // Recharger pour obtenir le media_url mis à jour
                $message->refresh();
            }

            // Gestion du cadeau
            $giftTransaction = null;
            if (!empty($validated['gift_id'])) {
                $gift = Gift::findOrFail($validated['gift_id']);

                // Vérifier le solde
                if ($sender->wallet_balance < $gift->price) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Solde insuffisant pour envoyer ce cadeau.',
                    ], 422);
                }

                // Calculer les montants
                $amounts = GiftTransaction::calculateAmounts($gift->price);

                // Débiter le wallet de l'expéditeur
                $sender->debitWallet(
                    $amounts['amount'],
                    "Cadeau envoyé : {$gift->name}",
                    null
                );

                // Créer la transaction cadeau
                $isAnonymous = !($validated['reveal_identity_with_gift'] ?? false);
                $giftTransaction = GiftTransaction::create([
                    'gift_id' => $gift->id,
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'anonymous_message_id' => $message->id,
                    'amount' => $amounts['amount'],
                    'platform_fee' => $amounts['platform_fee'],
                    'net_amount' => $amounts['net_amount'],
                    'status' => GiftTransaction::STATUS_COMPLETED,
                    'message' => $validated['gift_message'] ?? null,
                    'is_anonymous' => $isAnonymous,
                ]);

                // Créditer le wallet du destinataire
                $recipient->creditWallet(
                    $amounts['net_amount'],
                    "Cadeau reçu : {$gift->name}",
                    $giftTransaction
                );

                // Si révélation d'identité avec cadeau
                if (!$isAnonymous) {
                    $message->update([
                        'is_identity_revealed' => true,
                        'revealed_at' => now(),
                    ]);
                }

                \Log::info('🎁 Gift sent', [
                    'gift_id' => $gift->id,
                    'transaction_id' => $giftTransaction->id,
                    'is_anonymous' => $isAnonymous,
                ]);
            }

            // Créer une conversation si c'est une réponse
            $conversation = null;
            if ($createConversation && $originalMessage) {
                // Obtenir ou créer la conversation entre les deux utilisateurs
                $conversation = $sender->getOrCreateConversationWith($recipient);

                \Log::info('💬 Conversation obtained/created from anonymous reply', [
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                ]);

                // Épingler le message anonyme original dans la conversation
                $conversation->update([
                    'pinned_anonymous_message_id' => $originalMessage->id,
                ]);

                // Créer un message système dans la conversation
                $systemMessage = ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'content' => "Conversation démarrée depuis un message anonyme",
                    'type' => ChatMessage::TYPE_SYSTEM,
                    'anonymous_message_id' => $originalMessage->id,
                ]);

                \Log::info('📌 Anonymous message pinned in conversation', [
                    'conversation_id' => $conversation->id,
                    'pinned_message_id' => $originalMessage->id,
                ]);
            }

            DB::commit();

            // Déclencher l'événement
            event(new MessageSent($message));

            // Envoyer notification
            $this->notificationService->sendNewMessageNotification($message);

            // Envoyer SMS au destinataire si numéro valide
            if ($recipient->phone && strlen(trim($recipient->phone)) > 5) {
                try {
                    $content = $message->content ?: ($message->media_type === 'audio' ? '🎤 Message vocal' : '📷 Image');
                    $smsMessage = "📩 Nouveau message anonyme sur Weylo!\n\n"
                        . "« " . substr($content, 0, 100)
                        . (strlen($content) > 100 ? '...' : '') . " »\n\n"
                        . "Connectez-vous pour lire: " . config('app.frontend_url');

                    $this->nexahService->sendSms($recipient->phone, $smsMessage);
                    \Log::info("SMS envoyé au destinataire {$recipient->username} ({$recipient->phone})");
                } catch (\Exception $e) {
                    \Log::error("Erreur lors de l'envoi du SMS: " . $e->getMessage());
                }
            }

            // Préparer la réponse
            $responseData = [
                'message' => 'Message envoyé avec succès.',
                'data' => new MessageResource($message->load('giftTransactions.gift')),
            ];

            if ($conversation) {
                $responseData['conversation_id'] = $conversation->id;
            }

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error sending anonymous message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'envoi du message.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Révéler l'identité de l'expéditeur
     */
    public function reveal(Request $request, AnonymousMessage $message): JsonResponse
    {
        $user = $request->user();

        // Vérifications
        if ($message->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        if ($message->is_identity_revealed) {
            return response()->json([
                'message' => 'L\'identité a déjà été révélée.',
                'sender' => $message->sender_info,
            ]);
        }

        // Récupérer le prix de révélation depuis les settings
        $revealPrice = reveal_anonymous_price();

        // Vérifier si l'utilisateur a un solde suffisant
        if ($user->wallet_balance < $revealPrice) {
            return response()->json([
                'message' => 'Solde insuffisant pour révéler l\'identité.',
                'requires_payment' => true,
                'price' => $revealPrice,
                'current_balance' => $user->wallet_balance,
            ], 402);
        }

        // Effectuer la transaction dans une transaction DB
        try {
            DB::beginTransaction();

            // Débiter le wallet de l'utilisateur
            $balanceBefore = $user->wallet_balance;
            $user->wallet_balance -= $revealPrice;
            $user->save();

            // Créer la transaction wallet
            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => WalletTransaction::TYPE_DEBIT,
                'amount' => $revealPrice,
                'balance_before' => $balanceBefore,
                'balance_after' => $user->wallet_balance,
                'description' => 'Révélation d\'identité d\'un message anonyme',
                'reference' => 'REVEAL_' . $message->id . '_' . time(),
                'transactionable_type' => AnonymousMessage::class,
                'transactionable_id' => $message->id,
            ]);

            // Révéler l'identité
            $message->revealIdentity();

            DB::commit();

            return response()->json([
                'message' => 'Identité révélée avec succès.',
                'sender' => $message->fresh()->sender_info,
                'new_balance' => $user->wallet_balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur lors de la révélation d\'identité: ' . $e->getMessage());

            return response()->json([
                'message' => 'Une erreur est survenue lors de la révélation de l\'identité.',
            ], 500);
        }
    }

    /**
     * Supprimer un message
     */
    public function destroy(Request $request, AnonymousMessage $message): JsonResponse
    {
        $user = $request->user();

        // Seul le destinataire peut supprimer le message
        if ($message->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $message->delete();

        return response()->json([
            'message' => 'Message supprimé avec succès.',
        ]);
    }

    /**
     * Signaler un message
     */
    public function report(ReportMessageRequest $request, AnonymousMessage $message): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Vérifier que c'est le destinataire
        if ($message->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Vérifier si déjà signalé
        if ($message->isReportedBy($user)) {
            return response()->json([
                'message' => 'Vous avez déjà signalé ce message.',
            ], 422);
        }

        $report = $message->report($user, $validated['reason'], $validated['description'] ?? null);

        return response()->json([
            'message' => 'Signalement envoyé. Merci pour votre vigilance.',
        ], 201);
    }

    /**
     * Statistiques des messages
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'received_count' => $user->receivedMessages()->count(),
            'sent_count' => $user->sentMessages()->count(),
            'unread_count' => $user->receivedMessages()->unread()->count(),
            'revealed_count' => $user->receivedMessages()->where('is_identity_revealed', true)->count(),
        ]);
    }

    /**
     * Marquer tous les messages comme lus
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->receivedMessages()
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'Tous les messages ont été marqués comme lus.',
        ]);
    }

    /**
     * Répondre à un message anonyme
     * Cette méthode est spécifiquement pour répondre aux messages anonymes
     * sans avoir besoin de connaître le username du destinataire
     */
    public function sendReply(SendMessageRequest $request): JsonResponse
    {
        $sender = $request->user();
        $validated = $request->validated();

        // Vérifier que reply_to_message_id est fourni
        if (empty($validated['reply_to_message_id'])) {
            return response()->json([
                'message' => 'Le champ reply_to_message_id est requis.',
            ], 422);
        }

        // Récupérer le message original
        $originalMessage = AnonymousMessage::find($validated['reply_to_message_id']);

        if (!$originalMessage) {
            return response()->json([
                'message' => 'Message original introuvable.',
            ], 404);
        }

        // Vérifier que l'utilisateur actuel est le destinataire du message original
        if ($originalMessage->recipient_id !== $sender->id) {
            return response()->json([
                'message' => 'Vous ne pouvez répondre qu\'aux messages que vous avez reçus.',
            ], 422);
        }

        // Vérifier que le message original a un sender_id
        if (!$originalMessage->sender_id) {
            return response()->json([
                'message' => 'Impossible de répondre à ce message anonyme.',
            ], 422);
        }

        // Le destinataire de la réponse est l'expéditeur du message original
        $recipient = User::find($originalMessage->sender_id);

        if (!$recipient) {
            return response()->json([
                'message' => 'Destinataire introuvable.',
            ], 404);
        }

        // Vérifications
        if ($recipient->is_banned) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est plus disponible.',
            ], 422);
        }

        // Vérifier si bloqué
        if ($sender->isBlockedBy($recipient) || $sender->hasBlocked($recipient)) {
            return response()->json([
                'message' => 'Impossible d\'envoyer un message à cet utilisateur.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Préparation des données du message
            $messageData = [
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'content' => $validated['content'] ?? '',
                'reply_to_message_id' => $validated['reply_to_message_id'],
                'media_type' => $validated['media_type'] ?? 'none',
                'voice_type' => $validated['voice_type'] ?? 'normal',
            ];

            // Gestion du média (audio ou image)
            if ($request->hasFile('media')) {
                $media = $request->file('media');
                $mediaType = $validated['media_type'] ?? 'none';

                // Déterminer le dossier selon le type
                $folder = match($mediaType) {
                    'audio' => 'anonymous_messages/audio',
                    'image' => 'anonymous_messages/images',
                    default => 'anonymous_messages/media',
                };

                // Stocker le fichier
                $path = $media->store($folder, 'public');
                $messageData['media_url'] = $path;

                \Log::info('📎 Media uploaded', [
                    'type' => $mediaType,
                    'path' => $path,
                    'sender_id' => $sender->id,
                ]);
            }

            // Créer le message
            $message = AnonymousMessage::create($messageData);

            // Traitement vocal si c'est un audio avec effet (pas normal)
            if ($message->media_type === 'audio' && $message->voice_type !== 'normal' && $message->media_url) {
                \Log::info('🎤 Processing voice effect synchronously', [
                    'message_id' => $message->id,
                    'voice_type' => $message->voice_type,
                ]);

                // Traiter de manière synchrone avec le bon type de modèle
                \App\Jobs\ProcessVoiceEffect::dispatchSync(
                    $message->id,
                    $message->media_url,
                    $message->voice_type,
                    \App\Models\AnonymousMessage::class
                );

                // Recharger pour obtenir le media_url mis à jour
                $message->refresh();
            }

            // Gestion du cadeau
            $giftTransaction = null;
            if (!empty($validated['gift_id'])) {
                $gift = Gift::findOrFail($validated['gift_id']);

                // Vérifier le solde
                if ($sender->wallet_balance < $gift->price) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Solde insuffisant pour envoyer ce cadeau.',
                    ], 422);
                }

                // Calculer les montants
                $amounts = GiftTransaction::calculateAmounts($gift->price);

                // Débiter le wallet de l'expéditeur
                $sender->debitWallet(
                    $amounts['amount'],
                    "Cadeau envoyé : {$gift->name}",
                    null
                );

                // Créer la transaction cadeau
                $isAnonymous = !($validated['reveal_identity_with_gift'] ?? false);
                $giftTransaction = GiftTransaction::create([
                    'gift_id' => $gift->id,
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'anonymous_message_id' => $message->id,
                    'amount' => $amounts['amount'],
                    'platform_fee' => $amounts['platform_fee'],
                    'net_amount' => $amounts['net_amount'],
                    'status' => GiftTransaction::STATUS_COMPLETED,
                    'message' => $validated['gift_message'] ?? null,
                    'is_anonymous' => $isAnonymous,
                ]);

                // Créditer le wallet du destinataire
                $recipient->creditWallet(
                    $amounts['net_amount'],
                    "Cadeau reçu : {$gift->name}",
                    $giftTransaction
                );

                // Si révélation d'identité avec cadeau
                if (!$isAnonymous) {
                    $message->update([
                        'is_identity_revealed' => true,
                        'revealed_at' => now(),
                    ]);
                }

                \Log::info('🎁 Gift sent', [
                    'gift_id' => $gift->id,
                    'transaction_id' => $giftTransaction->id,
                    'is_anonymous' => $isAnonymous,
                ]);
            }

            // Révéler l'identité si demandé (même sans cadeau)
            if (!empty($validated['reveal_identity']) && $validated['reveal_identity']) {
                $message->update([
                    'is_identity_revealed' => true,
                    'revealed_at' => now(),
                ]);

                \Log::info('🔓 Identity revealed with message', [
                    'message_id' => $message->id,
                    'sender_id' => $sender->id,
                ]);
            }

            // Créer une conversation automatiquement (car c'est une réponse)
            $conversation = $sender->getOrCreateConversationWith($recipient);

            \Log::info('💬 Conversation obtained/created from anonymous reply', [
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
            ]);

            // Épingler le message anonyme original dans la conversation
            $conversation->update([
                'pinned_anonymous_message_id' => $originalMessage->id,
            ]);

            // Créer un message système dans la conversation
            $systemMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'content' => "Conversation démarrée depuis un message anonyme",
                'type' => ChatMessage::TYPE_SYSTEM,
                'anonymous_message_id' => $originalMessage->id,
            ]);

            // Créer également le message de réponse dans la conversation
            // Le message de réponse doit pointer vers le message ORIGINAL (pour le style reply-to)
            $chatMessage = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'content' => $message->content ?: '',
                'type' => $message->media_type === 'audio' ? ChatMessage::TYPE_AUDIO :
                         ($message->media_type === 'image' ? ChatMessage::TYPE_IMAGE : ChatMessage::TYPE_TEXT),
                'media_url' => $message->media_url,
                'anonymous_message_id' => $originalMessage->id, // Pointer vers le message original pour le reply-to
                'metadata' => $message->voice_type && $message->voice_type !== 'normal'
                    ? json_encode(['voice_type' => $message->voice_type])
                    : null,
            ]);

            \Log::info('💬 Chat message created from anonymous reply', [
                'chat_message_id' => $chatMessage->id,
                'anonymous_message_id' => $message->id,
                'conversation_id' => $conversation->id,
            ]);

            // Mettre à jour last_message_at pour que la conversation apparaisse dans la liste
            $conversation->update([
                'last_message_at' => now(),
            ]);

            \Log::info('📌 Anonymous message pinned in conversation', [
                'conversation_id' => $conversation->id,
                'pinned_message_id' => $originalMessage->id,
            ]);

            DB::commit();

            // Déclencher l'événement
            event(new MessageSent($message));

            // Envoyer notification
            $this->notificationService->sendNewMessageNotification($message);

            // Envoyer SMS au destinataire si numéro valide
            if ($recipient->phone && strlen(trim($recipient->phone)) > 5) {
                try {
                    $content = $message->content ?: ($message->media_type === 'audio' ? '🎤 Message vocal' : '📷 Image');
                    $smsMessage = "📩 Nouveau message anonyme sur Weylo!\n\n"
                        . "« " . substr($content, 0, 100)
                        . (strlen($content) > 100 ? '...' : '') . " »\n\n"
                        . "Connectez-vous pour lire: " . config('app.frontend_url');

                    $this->nexahService->sendSms($recipient->phone, $smsMessage);
                    \Log::info("SMS envoyé au destinataire {$recipient->username} ({$recipient->phone})");
                } catch (\Exception $e) {
                    \Log::error("Erreur lors de l'envoi du SMS: " . $e->getMessage());
                }
            }

            // Préparer la réponse
            $responseData = [
                'message' => 'Message envoyé avec succès.',
                'data' => new MessageResource($message->load('giftTransactions.gift')),
                'conversation_id' => $conversation->id,
            ];

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error sending anonymous message reply', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'envoi du message.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Démarrer une conversation à partir d'un message anonyme
     */
    public function startConversation(Request $request, AnonymousMessage $message): JsonResponse
    {
        $user = $request->user();

        // Vérifier que c'est bien le destinataire du message
        if ($message->recipient_id !== $user->id) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        // Obtenir l'expéditeur du message
        $sender = User::findOrFail($message->sender_id);

        // Vérifier les blocages
        if ($user->isBlockedBy($sender) || $user->hasBlocked($sender)) {
            return response()->json([
                'message' => 'Impossible de démarrer une conversation avec cet utilisateur.',
            ], 422);
        }

        // Créer ou obtenir la conversation
        $conversation = $user->getOrCreateConversationWith($sender);

        // Charger les relations nécessaires
        $conversation->load([
            'participantOne:id,first_name,last_name,username,avatar,last_seen_at',
            'participantTwo:id,first_name,last_name,username,avatar,last_seen_at',
        ]);

        $conversation->other_participant = $conversation->getOtherParticipant($user);
        $conversation->unread_count = $conversation->unreadCountFor($user);

        return response()->json([
            'message' => 'Conversation créée avec succès.',
            'conversation' => new \App\Http\Resources\ConversationResource($conversation),
        ], 201);
    }
}
