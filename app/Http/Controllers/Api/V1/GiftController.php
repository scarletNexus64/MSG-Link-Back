<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gift\SendGiftRequest;
use App\Http\Resources\GiftResource;
use App\Http\Resources\GiftTransactionResource;
use App\Http\Resources\GiftCategoryResource;
use App\Models\Gift;
use App\Models\GiftCategory;
use App\Models\GiftTransaction;
use App\Models\Conversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Events\GiftSent;
use App\Events\ChatMessageSent;
use App\Services\NotificationService;
use App\Services\Payment\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private PaymentServiceInterface $paymentService
    ) {}

    /**
     * Catalogue des cadeaux disponibles
     */
    public function index(): JsonResponse
    {
        $gifts = Gift::active()
            ->ordered()
            ->with('category') // Charger la relation category
            ->get();

        return response()->json([
            'gifts' => GiftResource::collection($gifts),
            'tiers' => [
                [
                    'name' => 'bronze',
                    'label' => 'Bronze',
                    'color' => '#CD7F32',
                ],
                [
                    'name' => 'silver',
                    'label' => 'Argent',
                    'color' => '#C0C0C0',
                ],
                [
                    'name' => 'gold',
                    'label' => 'Or',
                    'color' => '#FFD700',
                ],
                [
                    'name' => 'diamond',
                    'label' => 'Diamant',
                    'color' => '#B9F2FF',
                ],
            ],
        ]);
    }

    /**
     * Détail d'un cadeau
     */
    public function show(Gift $gift): JsonResponse
    {
        if (!$gift->is_active) {
            return response()->json([
                'message' => 'Cadeau non disponible.',
            ], 404);
        }

        return response()->json([
            'gift' => new GiftResource($gift),
        ]);
    }

    /**
     * Cadeaux reçus
     */
    public function received(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = GiftTransaction::byRecipient($user->id)
            ->completed()
            ->with([
                'gift',
                'sender:id,first_name,last_name,username,avatar',
                'conversation',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'transactions' => GiftTransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
            'stats' => [
                'total_received' => GiftTransaction::byRecipient($user->id)->completed()->count(),
                'total_value' => GiftTransaction::byRecipient($user->id)->completed()->sum('net_amount'),
            ],
        ]);
    }

    /**
     * Cadeaux envoyés
     */
    public function sent(Request $request): JsonResponse
    {
        $user = $request->user();

        $transactions = GiftTransaction::bySender($user->id)
            ->completed()
            ->with([
                'gift',
                'recipient:id,first_name,last_name,username,avatar',
                'conversation',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'transactions' => GiftTransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
            'stats' => [
                'total_sent' => GiftTransaction::bySender($user->id)->completed()->count(),
                'total_spent' => GiftTransaction::bySender($user->id)->completed()->sum('amount'),
            ],
        ]);
    }

    /**
     * Envoyer un cadeau (paiement par wallet)
     */
    public function send(SendGiftRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Récupérer le cadeau
        $gift = Gift::where('id', $validated['gift_id'])
            ->active()
            ->firstOrFail();

        // Récupérer le destinataire
        $recipient = User::where('username', $validated['recipient_username'])->firstOrFail();

        // Vérifications
        if ($user->id === $recipient->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas vous envoyer un cadeau.',
            ], 422);
        }

        if ($recipient->is_banned) {
            return response()->json([
                'message' => 'Cet utilisateur n\'est plus disponible.',
            ], 422);
        }

        if ($user->isBlockedBy($recipient) || $user->hasBlocked($recipient)) {
            return response()->json([
                'message' => 'Impossible d\'envoyer un cadeau à cet utilisateur.',
            ], 422);
        }

        // Vérifier le solde du wallet
        if (!$user->hasEnoughBalance($gift->price)) {
            return response()->json([
                'message' => 'Solde insuffisant. Veuillez recharger votre wallet.',
                'required_amount' => $gift->price,
                'current_balance' => $user->wallet_balance,
            ], 422);
        }

        // Obtenir ou créer la conversation
        $conversation = $user->getOrCreateConversationWith($recipient);

        // Calculer les montants
        $amounts = GiftTransaction::calculateAmounts($gift->price);

        try {
            DB::beginTransaction();

            // Débiter le wallet de l'expéditeur
            $user->debitWallet(
                $gift->price,
                "Envoi cadeau : {$gift->name}",
                null
            );

            // Créer la transaction complétée
            $transaction = GiftTransaction::create([
                'gift_id' => $gift->id,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'conversation_id' => $conversation->id,
                'amount' => $amounts['amount'],
                'platform_fee' => $amounts['platform_fee'],
                'net_amount' => $amounts['net_amount'],
                'status' => GiftTransaction::STATUS_COMPLETED,
                'message' => $validated['message'] ?? null,
                'is_anonymous' => $validated['is_anonymous'] ?? false,
            ]);

            // Créditer le wallet du destinataire
            $recipient->creditWallet(
                $amounts['net_amount'],
                "Cadeau reçu : {$gift->name}",
                $transaction
            );

            // Créer le message de cadeau dans la conversation
            $chatMessage = ChatMessage::createGiftMessage(
                $conversation,
                $user,
                $transaction,
                $validated['message'] ?? null
            );

            // Mettre à jour la conversation
            $conversation->updateAfterMessage();

            DB::commit();

            // Diffuser les événements
            try {
                event(new GiftSent($transaction));

                // Diffuser le message dans la conversation via WebSocket
                $chatMessage->load(['sender', 'giftTransaction.gift']);
                event(new ChatMessageSent($chatMessage, $recipient->id));
            } catch (\Exception $e) {
                \Log::warning('Broadcasting failed for gift: ' . $e->getMessage());
            }

            // Notification
            $this->notificationService->sendGiftNotification($transaction);

            return response()->json([
                'message' => 'Cadeau envoyé avec succès.',
                'transaction' => new GiftTransactionResource($transaction->fresh(['gift', 'recipient', 'sender'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de l\'envoi du cadeau.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmer l'envoi d'un cadeau (après paiement réussi)
     * Cette méthode est généralement appelée par le webhook de paiement
     */
    public function confirm(Request $request, GiftTransaction $transaction): JsonResponse
    {
        // Vérifier que la transaction est en attente
        if ($transaction->status !== GiftTransaction::STATUS_PENDING) {
            return response()->json([
                'message' => 'Transaction déjà traitée.',
            ], 422);
        }

        DB::transaction(function () use ($transaction) {
            // Marquer comme complété et créditer le destinataire
            $transaction->markAsCompleted();

            // Créer le message de cadeau dans la conversation
            $chatMessage = ChatMessage::createGiftMessage(
                $transaction->conversation,
                $transaction->sender,
                $transaction,
                $transaction->message
            );

            // Mettre à jour la conversation
            $transaction->conversation->updateAfterMessage();

            // Diffuser les événements
            try {
                event(new GiftSent($transaction));
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas l'envoi du cadeau
                \Log::warning('Broadcasting failed for gift: ' . $e->getMessage());
            }

            // Notification
            $this->notificationService->sendGiftNotification($transaction);
        });

        return response()->json([
            'message' => 'Cadeau envoyé avec succès.',
            'transaction' => new GiftTransactionResource($transaction->fresh()),
        ]);
    }

    /**
     * Envoyer un cadeau dans une conversation existante (paiement par wallet)
     */
    public function sendInConversation(SendGiftRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasParticipant($user)) {
            return response()->json([
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        $validated = $request->validated();
        $gift = Gift::where('id', $validated['gift_id'])->active()->firstOrFail();
        $recipient = $conversation->getOtherParticipant($user);

        // Vérifier le solde du wallet
        if (!$user->hasEnoughBalance($gift->price)) {
            return response()->json([
                'message' => 'Solde insuffisant. Veuillez recharger votre wallet.',
                'required_amount' => $gift->price,
                'current_balance' => $user->wallet_balance,
            ], 422);
        }

        // Calculer les montants
        $amounts = GiftTransaction::calculateAmounts($gift->price);

        try {
            DB::beginTransaction();

            // Débiter le wallet de l'expéditeur
            $user->debitWallet(
                $gift->price,
                "Envoi cadeau : {$gift->name}",
                null
            );

            // Créer la transaction complétée
            $transaction = GiftTransaction::create([
                'gift_id' => $gift->id,
                'sender_id' => $user->id,
                'recipient_id' => $recipient->id,
                'conversation_id' => $conversation->id,
                'amount' => $amounts['amount'],
                'platform_fee' => $amounts['platform_fee'],
                'net_amount' => $amounts['net_amount'],
                'status' => GiftTransaction::STATUS_COMPLETED,
                'message' => $validated['message'] ?? null,
                'is_anonymous' => $validated['is_anonymous'] ?? false,
            ]);

            // Créditer le wallet du destinataire
            $recipient->creditWallet(
                $amounts['net_amount'],
                "Cadeau reçu : {$gift->name}",
                $transaction
            );

            // Créer le message de cadeau dans la conversation
            $chatMessage = ChatMessage::createGiftMessage(
                $conversation,
                $user,
                $transaction,
                $validated['message'] ?? null
            );

            // Mettre à jour la conversation
            $conversation->updateAfterMessage();

            DB::commit();

            // Diffuser les événements
            try {
                event(new GiftSent($transaction));

                // Diffuser le message dans la conversation via WebSocket
                $chatMessage->load(['sender', 'giftTransaction.gift']);
                event(new ChatMessageSent($chatMessage, $recipient->id));
            } catch (\Exception $e) {
                \Log::warning('Broadcasting failed for gift: ' . $e->getMessage());
            }

            // Notification
            $this->notificationService->sendGiftNotification($transaction);

            return response()->json([
                'message' => 'Cadeau envoyé avec succès.',
                'transaction' => new GiftTransactionResource($transaction->fresh(['gift', 'recipient', 'sender'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de l\'envoi du cadeau.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistiques des cadeaux
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'received' => [
                'count' => GiftTransaction::byRecipient($user->id)->completed()->count(),
                'total_value' => GiftTransaction::byRecipient($user->id)->completed()->sum('net_amount'),
            ],
            'sent' => [
                'count' => GiftTransaction::bySender($user->id)->completed()->count(),
                'total_spent' => GiftTransaction::bySender($user->id)->completed()->sum('amount'),
            ],
            'by_tier' => [
                'bronze' => GiftTransaction::byRecipient($user->id)
                    ->completed()
                    ->whereHas('gift', fn($q) => $q->where('tier', Gift::TIER_BRONZE))
                    ->count(),
                'silver' => GiftTransaction::byRecipient($user->id)
                    ->completed()
                    ->whereHas('gift', fn($q) => $q->where('tier', Gift::TIER_SILVER))
                    ->count(),
                'gold' => GiftTransaction::byRecipient($user->id)
                    ->completed()
                    ->whereHas('gift', fn($q) => $q->where('tier', Gift::TIER_GOLD))
                    ->count(),
                'diamond' => GiftTransaction::byRecipient($user->id)
                    ->completed()
                    ->whereHas('gift', fn($q) => $q->where('tier', Gift::TIER_DIAMOND))
                    ->count(),
            ],
        ]);
    }

    /**
     * Liste toutes les catégories de cadeaux actives
     */
    public function getCategories(): JsonResponse
    {
        $categories = GiftCategory::where('is_active', true)
            ->withCount('gifts')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => GiftCategoryResource::collection($categories),
        ]);
    }

    /**
     * Récupère les cadeaux d'une catégorie spécifique
     */
    public function getGiftsByCategory($categoryId): JsonResponse
    {
        $category = GiftCategory::where('is_active', true)->findOrFail($categoryId);

        $gifts = Gift::where('is_active', true)
            ->where('gift_category_id', $categoryId)
            ->orderBy('price')
            ->get();

        return response()->json([
            'category' => new GiftCategoryResource($category),
            'gifts' => GiftResource::collection($gifts),
        ]);
    }
}
