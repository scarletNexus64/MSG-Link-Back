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
                    'icon' => $gift?->icon ?? '🎁',
                    'emoji_image_url' => $gift?->emoji_image_url, // URL de l'image Twemoji
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

            // Si c'est une réponse à une story
            'story' => $this->when($this->relationLoaded('story') && $this->story, function () {
                return [
                    'id' => $this->story->id,
                    'type' => $this->story->type,
                    'content' => $this->story->content,
                    'media_url' => $this->story->media_full_url,
                    'thumbnail_url' => $this->story->thumbnail_full_url,
                    'background_color' => $this->story->background_color,
                    'created_at' => $this->story->created_at->toIso8601String(),
                    'user' => $this->when($this->story->relationLoaded('user') && $this->story->user, [
                        'id' => $this->story->user->id,
                        'username' => $this->story->user->username,
                        'full_name' => $this->story->user->full_name,
                        'avatar_url' => $this->story->user->avatar_url,
                    ]),
                ];
            }),

            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),

            // Édition de message
            'edited_at' => $this->edited_at?->toIso8601String(),
            'is_edited' => $this->edited_at !== null,

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
