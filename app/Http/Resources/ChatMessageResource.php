<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isMine = $this->sender_id === $user?->id;

        // Vérifier si l'utilisateur a un premium pour cette conversation
        $hasPremium = $this->conversation?->hasPremiumSubscription($user);

        // Si le sender est supprimé, retourner des données par défaut
        $senderData = null;
        if ($this->sender) {
            $senderData = [
                'id' => $this->sender->id,
                'initial' => $this->sender->initial,
                'first_name' => ($isMine || $hasPremium) ? $this->sender->first_name : null,
                'avatar_url' => ($isMine || $hasPremium) ? $this->sender->avatar_url : null,
            ];
        } else {
            // Sender supprimé - retourner des données anonymes
            $senderData = [
                'id' => null,
                'initial' => '?',
                'first_name' => null,
                'avatar_url' => null,
            ];
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'content' => $this->content,
            'type' => $this->type,
            'media_url' => $this->media_url,
            'metadata' => $this->metadata,
            'is_mine' => $isMine,

            // Expéditeur
            'sender' => $senderData,

            // Si c'est un message cadeau
            'gift_data' => $this->when($this->type === 'gift' && $this->relationLoaded('giftTransaction'), function () {
                $gift = $this->giftTransaction?->gift;
                return [
                    'id' => $gift?->id,
                    'name' => $gift?->name,
                    'icon' => $gift?->icon,
                    'animation' => $gift?->animation,
                    'price' => $gift?->price,
                    'formatted_price' => $gift?->formatted_price,
                    'tier' => $gift?->tier,
                    'tier_color' => $gift?->tier_color,
                    'background_color' => $gift?->background_color,
                    'description' => $gift?->description,
                    'amount' => $this->giftTransaction?->amount,
                    'is_anonymous' => $this->giftTransaction?->is_anonymous ?? false,
                ];
            }),

            // Si c'est une réponse à un message anonyme
            'anonymous_message' => $this->when($this->relationLoaded('anonymousMessage') && $this->anonymousMessage, function () {
                return [
                    'id' => $this->anonymousMessage->id,
                    'content' => $this->anonymousMessage->content,
                    'created_at' => $this->anonymousMessage->created_at->toIso8601String(),
                ];
            }),

            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
