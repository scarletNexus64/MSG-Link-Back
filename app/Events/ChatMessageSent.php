<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $message;
    public int $receiverId;

    public function __construct(ChatMessage $message, int $receiverId)
    {
        $this->message = $message;
        $this->receiverId = $receiverId;

        \Log::info('🔊 [EVENT] ChatMessageSent créé', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $receiverId,
            'type' => $message->type,
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            // Canal de la conversation spécifique (pour les utilisateurs dans la conversation)
            new PrivateChannel('conversation.' . $this->message->conversation_id),

            // ✨ NOUVEAU: Canal global du destinataire (pour le badge count et notifications globales)
            new PrivateChannel('user.' . $this->receiverId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_initial' => $this->message->sender->initial,
            'content' => $this->message->content,
            'type' => $this->message->type,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];

        // Ajouter les données du cadeau si c'est un message de type gift
        if ($this->message->type === ChatMessage::TYPE_GIFT && $this->message->giftTransaction) {
            $gift = $this->message->giftTransaction->gift;
            $data['gift_data'] = [
                'id' => $gift->id,
                'name' => $gift->name,
                'icon' => $gift->icon,
                'price' => $gift->price,
                'formatted_price' => $gift->formatted_price,
                'tier' => $gift->tier,
                'background_color' => $gift->background_color,
                'description' => $gift->description,
                'is_anonymous' => $this->message->giftTransaction->is_anonymous,
            ];
        }

        // Ajouter le message anonyme cité si présent (réponse à un message anonyme)
        if ($this->message->anonymousMessage) {
            $data['anonymous_message'] = [
                'id' => $this->message->anonymousMessage->id,
                'content' => $this->message->anonymousMessage->content,
                'created_at' => $this->message->anonymousMessage->created_at->toIso8601String(),
            ];
        }

        return $data;
    }
}
