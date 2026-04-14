<?php

namespace App\Services;

use App\Models\User;
use App\Models\AnonymousMessage;
use App\Models\Confession;
use App\Models\ChatMessage;
use App\Models\GiftTransaction;
use App\Models\Withdrawal;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FCMNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;

class NotificationService
{
    private $messaging = null;

    public function __construct()
    {
        // Initialiser Firebase si configuré
        if (config('services.firebase.credentials')) {
            try {
                $factory = (new Factory)->withServiceAccount(config('services.firebase.credentials'));
                $this->messaging = $factory->createMessaging();
            } catch (\Exception $e) {
                Log::warning('Firebase not configured: ' . $e->getMessage());
            }
        }
    }

    /**
     * Créer une notification en base de données
     */
    public function createNotification(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): Notification {
        return Notification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    /**
     * Envoyer une notification push via FCM
     */
    public function sendPushNotification(User $user, string $title, string $body, array $data = [], ?string $imageUrl = null): bool
    {
        if (!$this->messaging || !$user->fcm_token) {
            return false;
        }

        try {
            // Créer la notification de base avec image si fournie
            $notification = $imageUrl
                ? FCMNotification::create($title, $body)->withImageUrl($imageUrl)
                : FCMNotification::create($title, $body);

            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification($notification)
                ->withData($data);

            // Configuration Android : icône et couleur
            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'icon' => 'ic_notification_white',  // Icône blanche XML (SANS préfixe @drawable/ pour FCM)
                    'color' => '#FF1493',          // Couleur rose/violet (gradient de votre logo)
                    'sound' => 'default',
                    'channel_id' => 'weylo_notifications',  // Doit correspondre au channel créé dans l'app
                ],
            ]);

            // Configuration iOS/APNs
            $apnsConfig = ApnsConfig::fromArray([
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ]);

            $message = $message
                ->withAndroidConfig($androidConfig)
                ->withApnsConfig($apnsConfig);

            $this->messaging->send($message);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // Si le token est invalide, le supprimer
            if (str_contains($e->getMessage(), 'not a valid FCM registration token')) {
                $user->update(['fcm_token' => null]);
            }

            return false;
        }
    }

    /**
     * Notification de nouveau message anonyme
     */
    public function sendNewMessageNotification(AnonymousMessage $message): void
    {
        $recipient = $message->recipient;

        // Ne pas envoyer de notification si l'utilisateur s'envoie un message à lui-même
        if ($message->sender_id === $message->recipient_id) {
            return;
        }

        // Notification en base
        $this->createNotification(
            $recipient,
            'new_message',
            'Nouveau message anonyme',
            "Quelqu'un vous a envoyé un message anonyme.",
            [
                'message_id' => $message->id,
                'action' => 'view_message',
            ]
        );

        // Notification push
        $this->sendPushNotification(
            $recipient,
            'Nouveau message anonyme',
            "Quelqu'un vous a envoyé un message.",
            [
                'type' => 'new_message',
                'message_id' => (string) $message->id,
            ]
        );
    }

    /**
     * Notification de nouvelle confession
     */
    public function sendNewConfessionNotification(Confession $confession): void
    {
        if (!$confession->recipient) {
            return;
        }

        $recipient = $confession->recipient;

        // Ne pas envoyer de notification si l'utilisateur s'envoie une confession à lui-même
        if ($confession->author_id === $confession->recipient_id) {
            return;
        }

        $this->createNotification(
            $recipient,
            'new_confession',
            'Nouvelle confession',
            "Quelqu'un vous a fait une confession.",
            [
                'confession_id' => $confession->id,
                'action' => 'view_confession',
            ]
        );

        $this->sendPushNotification(
            $recipient,
            'Nouvelle confession',
            "Quelqu'un vous a fait une confession anonyme.",
            [
                'type' => 'new_confession',
                'confession_id' => (string) $confession->id,
            ]
        );
    }

    /**
     * Notification de message de chat
     */
    public function sendChatMessageNotification(ChatMessage $message): void
    {
        $conversation = $message->conversation;
        $recipient = $conversation->getOtherParticipant($message->sender);

        // Ne pas envoyer de notification si l'utilisateur s'envoie un message à lui-même (sécurité)
        if ($message->sender_id === $recipient->id) {
            return;
        }

        // Toujours rester mystérieux pour garder l'anonymat
        $this->createNotification(
            $recipient,
            'new_chat_message',
            'Nouveau message',
            "Quelqu'un vous a envoyé un message.",
            [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'action' => 'open_chat',
            ]
        );

        $this->sendPushNotification(
            $recipient,
            'Nouveau message',
            "Quelqu'un vous a envoyé un message.",
            [
                'type' => 'new_chat_message',
                'conversation_id' => (string) $conversation->id,
            ]
        );
    }

    /**
     * Notification de cadeau reçu
     */
    public function sendGiftNotification(GiftTransaction $transaction): void
    {
        $recipient = $transaction->recipient;
        $gift = $transaction->gift;

        // Ne pas envoyer de notification si l'utilisateur s'envoie un cadeau à lui-même
        if ($transaction->sender_id === $transaction->recipient_id) {
            return;
        }

        $this->createNotification(
            $recipient,
            'gift_received',
            'Cadeau reçu !',
            "Vous avez reçu un cadeau : {$gift->name} ({$transaction->formatted_amount})",
            [
                'transaction_id' => $transaction->id,
                'gift_id' => $gift->id,
                'amount' => $transaction->net_amount,
                'action' => 'view_gift',
            ]
        );

        $this->sendPushNotification(
            $recipient,
            'Cadeau reçu !',
            "Vous avez reçu un {$gift->name} !", 
            [
                'type' => 'gift_received',
                'transaction_id' => (string) $transaction->id,
            ]
        );
    }

    /**
     * Notification de retrait traité
     */
    public function sendWithdrawalProcessedNotification(Withdrawal $withdrawal): void
    {
        $user = $withdrawal->user;

        $this->createNotification(
            $user,
            'withdrawal_processed',
            'Retrait effectué',
            "Votre retrait de {$withdrawal->formatted_net_amount} a été effectué avec succès.",
            [
                'withdrawal_id' => $withdrawal->id,
                'amount' => $withdrawal->net_amount,
                'action' => 'view_wallet',
            ]
        );

        $this->sendPushNotification(
            $user,
            'Retrait effectué',
            "Votre retrait de {$withdrawal->formatted_net_amount} est en cours.",
            [
                'type' => 'withdrawal_processed',
                'withdrawal_id' => (string) $withdrawal->id,
            ]
        );
    }

    /**
     * Notification de retrait rejeté
     */
    public function sendWithdrawalRejectedNotification(Withdrawal $withdrawal): void
    {
        $user = $withdrawal->user;

        $this->createNotification(
            $user,
            'withdrawal_rejected',
            'Retrait refusé',
            "Votre demande de retrait de {$withdrawal->formatted_amount} a été refusée. Raison: {$withdrawal->rejection_reason}",
            [
                'withdrawal_id' => $withdrawal->id,
                'reason' => $withdrawal->rejection_reason,
                'action' => 'view_wallet',
            ]
        );

        $this->sendPushNotification(
            $user,
            'Retrait refusé',
            "Votre demande de retrait a été refusée.",
            [
                'type' => 'withdrawal_rejected',
                'withdrawal_id' => (string) $withdrawal->id,
            ]
        );
    }

    /**
     * Notification de retrait échoué
     */
    public function sendWithdrawalFailedNotification(Withdrawal $withdrawal): void
    {
        $user = $withdrawal->user;

        $reason = $withdrawal->rejection_reason ?? 'Une erreur est survenue';

        $this->createNotification(
            $user,
            'withdrawal_failed',
            'Retrait échoué',
            "Votre retrait de {$withdrawal->formatted_amount} a échoué. Raison: {$reason}",
            [
                'withdrawal_id' => $withdrawal->id,
                'reason' => $reason,
                'action' => 'view_wallet',
            ]
        );

        $this->sendPushNotification(
            $user,
            'Retrait échoué',
            "Votre retrait a échoué. Veuillez réessayer.",
            [
                'type' => 'withdrawal_failed',
                'withdrawal_id' => (string) $withdrawal->id,
            ]
        );
    }

    /**
     * Notification de dépôt complété
     */
    public function sendDepositCompletedNotification(\App\Models\Transaction $transaction): void
    {
        $user = $transaction->user;

        $formattedAmount = number_format($transaction->amount, 0, ',', ' ') . ' FCFA';

        $this->createNotification(
            $user,
            'deposit_completed',
            'Dépôt réussi',
            "Votre dépôt de {$formattedAmount} a été effectué avec succès.",
            [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'action' => 'view_wallet',
            ]
        );

        $this->sendPushNotification(
            $user,
            'Dépôt réussi',
            "Votre compte a été crédité de {$formattedAmount}",
            [
                'type' => 'deposit_completed',
                'transaction_id' => (string) $transaction->id,
            ]
        );
    }

    /**
     * Notification de dépôt échoué
     */
    public function sendDepositFailedNotification(\App\Models\Transaction $transaction, string $reason = null): void
    {
        $user = $transaction->user;

        $formattedAmount = number_format($transaction->amount, 0, ',', ' ') . ' FCFA';
        $failureReason = $reason ?? 'Une erreur est survenue';

        $this->createNotification(
            $user,
            'deposit_failed',
            'Dépôt échoué',
            "Votre dépôt de {$formattedAmount} a échoué. Raison: {$failureReason}",
            [
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
                'reason' => $failureReason,
                'action' => 'view_wallet',
            ]
        );

        $this->sendPushNotification(
            $user,
            'Dépôt échoué',
            "Votre dépôt de {$formattedAmount} a échoué.",
            [
                'type' => 'deposit_failed',
                'transaction_id' => (string) $transaction->id,
            ]
        );
    }

    /**
     * Notification d'abonnement expirant bientôt
     */
    public function sendSubscriptionExpiringNotification(User $user, int $daysRemaining): void
    {
        $this->createNotification(
            $user,
            'subscription_expiring',
            'Abonnement expirant',
            "Votre abonnement premium expire dans {$daysRemaining} jour(s).",
            [
                'days_remaining' => $daysRemaining,
                'action' => 'manage_subscription',
            ]
        );

        $this->sendPushNotification(
            $user,
            'Abonnement expirant',
            "Votre abonnement premium expire dans {$daysRemaining} jour(s).",
            [
                'type' => 'subscription_expiring',
            ]
        );
    }

    /**
     * Notification de bienvenue (inscription)
     */
    public function sendWelcomeNotification(User $user): void
    {
        // Notification en base
        $this->createNotification(
            $user,
            'welcome',
            'Bienvenue sur Weylo !',
            "Bonjour {$user->first_name} ! Découvrez toutes les fonctionnalités de Weylo : messages anonymes, confessions, stories et plus encore !",
            [
                'action' => 'explore_app',
            ]
        );

        // Notification push
        $this->sendPushNotification(
            $user,
            'Bienvenue sur Weylo !',
            "Bonjour {$user->first_name} ! Découvrez toutes les fonctionnalités de l'application.",
            [
                'type' => 'welcome',
            ]
        );
    }

    /**
     * Envoyer une notification push par topic
     */
    public function sendTopicNotification(string $topic, string $title, string $body, array $data = [], ?string $imageUrl = null): bool
    {
        if (!$this->messaging) {
            return false;
        }

        try {
            // Créer la notification de base avec image si fournie
            $notification = $imageUrl
                ? FCMNotification::create($title, $body)->withImageUrl($imageUrl)
                : FCMNotification::create($title, $body);

            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            // Configuration Android : icône et couleur
            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'icon' => 'ic_notification_white',  // Icône blanche XML (SANS préfixe @drawable/ pour FCM)
                    'color' => '#FF1493',          // Couleur rose/violet (gradient de votre logo)
                    'sound' => 'default',
                    'channel_id' => 'weylo_notifications',  // Doit correspondre au channel créé dans l'app
                ],
            ]);

            // Configuration iOS/APNs
            $apnsConfig = ApnsConfig::fromArray([
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ]);

            $message = $message
                ->withAndroidConfig($androidConfig)
                ->withApnsConfig($apnsConfig);

            $this->messaging->send($message);

            Log::info("📢 Topic notification sent to: {$topic}", [
                'title' => $title,
                'data' => $data,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM topic notification failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Notification de nouvelle confession publique (par topic)
     */
    public function sendNewPublicConfessionTopicNotification(\App\Models\Confession $confession): void
    {
        if ($confession->type !== 'public' || $confession->moderation_status !== 'approved') {
            return;
        }

        $this->sendTopicNotification(
            'new_confessions',
            'Nouvelle confession publique',
            "Une nouvelle confession est disponible !",
            [
                'type' => 'new_public_confession',
                'confession_id' => (string) $confession->id,
            ]
        );
    }

    /**
     * Notification de nouvelle story (par topic)
     */
    public function sendNewStoryTopicNotification(\App\Models\Story $story): void
    {
        // Toujours rester mystérieux pour créer du suspense
        $this->sendTopicNotification(
            'new_stories',
            'Nouvelle story !',
            "Quelqu'un a publié une nouvelle story mystérieuse !",
            [
                'type' => 'new_story',
                'story_id' => (string) $story->id,
                'user_id' => (string) $story->user_id,
            ]
        );
    }

    /**
     * Notification de like sur une story
     */
    public function sendStoryLikeNotification(\App\Models\Story $story): void
    {
        $recipient = $story->user;

        if (!$recipient) {
            return;
        }

        // Notification simple sans mentionner qui a liké
        $this->sendPushNotification(
            $recipient,
            'Votre story a été liké',
            'Votre story a été liké',
            [
                'type' => 'story_like',
                'story_id' => (string) $story->id,
            ]
        );
    }

    /**
     * Notification de réponse à une story
     */
    public function sendStoryReplyNotification(\App\Models\Story $story, \App\Models\ChatMessage $message): void
    {
        $recipient = $story->user;

        if (!$recipient) {
            return;
        }

        // Ne pas envoyer de notification si l'utilisateur répond à sa propre story
        if ($message->sender_id === $story->user_id) {
            return;
        }

        // Toujours rester mystérieux
        $this->createNotification(
            $recipient,
            'story_reply',
            'Réponse à votre story',
            "Quelqu'un a répondu à votre story !",
            [
                'story_id' => $story->id,
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'action' => 'open_chat',
            ]
        );

        $this->sendPushNotification(
            $recipient,
            'Réponse à votre story',
            "Quelqu'un a répondu à votre story mystérieuse !",
            [
                'type' => 'story_reply',
                'story_id' => (string) $story->id,
                'conversation_id' => (string) $message->conversation_id,
            ]
        );
    }

    /**
     * Notification de commentaire sur une confession
     */
    public function sendConfessionCommentNotification(\App\Models\Confession $confession, \App\Models\ConfessionComment $comment): void
    {
        // Déterminer qui notifier
        $recipient = null;
        $notificationTitle = '';
        $notificationBody = '';

        if ($comment->parent_id) {
            // C'est une réponse à un commentaire
            $parentComment = \App\Models\ConfessionComment::find($comment->parent_id);
            if ($parentComment && $parentComment->author_id !== $comment->author_id) {
                $recipient = $parentComment->author;
                $notificationTitle = 'Réponse à votre commentaire';
                $notificationBody = "Quelqu'un a répondu à votre commentaire !";
            }
        } else {
            // C'est un commentaire direct sur la confession
            // Notifier l'auteur de la confession seulement si ce n'est pas lui qui commente
            if ($confession->author_id && $confession->author_id !== $comment->author_id) {
                $recipient = $confession->author;
                $notificationTitle = 'Nouveau commentaire';
                $notificationBody = "Quelqu'un a commenté votre confession !";
            }
        }

        if (!$recipient) {
            return;
        }

        // Toujours rester mystérieux
        $this->createNotification(
            $recipient,
            'confession_comment',
            $notificationTitle,
            $notificationBody,
            [
                'confession_id' => $confession->id,
                'comment_id' => $comment->id,
                'action' => 'view_confession',
            ]
        );

        $this->sendPushNotification(
            $recipient,
            $notificationTitle,
            $notificationBody,
            [
                'type' => 'confession_comment',
                'confession_id' => (string) $confession->id,
                'comment_id' => (string) $comment->id,
            ]
        );
    }

    /**
     * Souscrire un utilisateur à un topic
     */
    public function subscribeToTopic(string $fcmToken, string $topic): bool
    {
        if (!$this->messaging) {
            return false;
        }

        try {
            $this->messaging->subscribeToTopic($topic, [$fcmToken]);

            Log::info("✅ User subscribed to topic: {$topic}", [
                'token' => substr($fcmToken, 0, 20) . '...',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM topic subscription failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Se désabonner d'un topic
     */
    public function unsubscribeFromTopic(string $fcmToken, string $topic): bool
    {
        if (!$this->messaging) {
            return false;
        }

        try {
            $this->messaging->unsubscribeFromTopic($topic, [$fcmToken]);

            Log::info("❌ User unsubscribed from topic: {$topic}", [
                'token' => substr($fcmToken, 0, 20) . '...',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('FCM topic unsubscription failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Souscrire un utilisateur à tous les topics
     */
    public function subscribeToAllTopics(User $user): void
    {
        if (!$user->fcm_token) {
            return;
        }

        // Topics disponibles
        $topics = [
            'global_announcements',  // Annonces globales de l'admin
            'new_confessions',       // Nouvelles confessions publiques
            'new_stories',           // Nouvelles stories
        ];

        foreach ($topics as $topic) {
            $this->subscribeToTopic($user->fcm_token, $topic);
        }

        Log::info("✅ User {$user->id} subscribed to all topics", ['topics' => $topics]);
    }

    /**
     * Notification de vue de profil (admirateur secret)
     */
    public function sendProfileViewNotification(User $user): void
    {
        // Notification en base
        $this->createNotification(
            $user,
            'profile_view',
            'Admirateur secret',
            "Quelqu'un a consulté votre profil !",
            [
                'action' => 'view_profile_visitors',
            ]
        );

        // Notification push
        $this->sendPushNotification(
            $user,
            'Admirateur secret',
            "Quelqu'un a consulté votre profil mystérieusement !",
            [
                'type' => 'profile_view',
            ]
        );
    }
}
