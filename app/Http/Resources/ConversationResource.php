<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $otherParticipant = $this->other_participant ?? $this->getOtherParticipant($user);

        // Si l'autre participant est supprimé, retourner des données minimales
        if (!$otherParticipant) {
            return [
                'id' => $this->id,
                'other_participant' => null,
                'last_message' => null,
                'streak' => [
                    'count' => 0,
                    'flame_level' => 'none',
                    'streak_updated_at' => null,
                ],
                'identity_revealed' => false,
                'has_premium' => false,
                'unread_count' => 0,
                'last_message_at' => $this->last_message_at?->toIso8601String(),
                'created_at' => $this->created_at->toIso8601String(),
            ];
        }

        // Vérifier si l'identité peut être révélée (premium actif ou payé pour révéler)
        $canViewIdentity = $user->has_active_premium || $this->isIdentityRevealedFor($user);

        // Récupérer l'anonymous_message_id depuis le premier message de la conversation
        $anonymousMessageId = $this->messages()->orderBy('created_at')->value('anonymous_message_id');

        // Une conversation est anonyme si elle a été créée depuis un message anonyme
        $isAnonymous = $anonymousMessageId !== null || $this->pinned_anonymous_message_id !== null;

        // Vérifier si l'utilisateur actuel peut initier un paiement pour révéler l'identité
        // N'importe quel participant de la conversation peut payer pour révéler l'autre
        // Conditions:
        // 1. L'identité n'est pas encore révélée pour cet utilisateur
        // 2. L'utilisateur n'est pas premium (les premium voient automatiquement)
        $canInitiateReveal = !$canViewIdentity;

        return [
            'id' => $this->id,

            // L'autre participant
            'other_participant' => [
                'id' => $otherParticipant->id,
                'first_name' => $canViewIdentity ? $otherParticipant->first_name : null,
                'last_name' => $canViewIdentity ? $otherParticipant->last_name : null,
                'full_name' => $canViewIdentity ? $otherParticipant->full_name : null,
                'username' => $canViewIdentity ? $otherParticipant->username : null,
                'initial' => $otherParticipant->initial,
                'avatar_url' => $otherParticipant->avatar_url, // Avatar toujours visible
                'is_online' => $otherParticipant->is_online,
                'is_premium' => $otherParticipant->is_premium,
                'last_seen_at' => $otherParticipant->last_seen_at?->toIso8601String(),
            ],

            // Dernier message
            'last_message' => $this->when($this->relationLoaded('lastMessage'), function () {
                $giftData = null;
                if ($this->lastMessage?->type === 'gift' && $this->lastMessage?->relationLoaded('giftTransaction')) {
                    $gift = $this->lastMessage?->giftTransaction?->gift;
                    $giftData = [
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
                        'amount' => $this->lastMessage?->giftTransaction?->amount,
                        'is_anonymous' => $this->lastMessage?->giftTransaction?->is_anonymous ?? false,
                    ];
                }

                return [
                    'id' => $this->lastMessage?->id,
                    'content' => $this->lastMessage?->content_preview,
                    'type' => $this->lastMessage?->type,
                    'is_mine' => $this->lastMessage?->sender_id === request()->user()?->id,
                    'created_at' => $this->lastMessage?->created_at?->toIso8601String(),
                    'gift_data' => $giftData,
                ];
            }),

            // Système Flame
            'streak' => [
                'count' => $this->streak_count,
                'flame_level' => $this->flame_level,
                'streak_updated_at' => $this->streak_updated_at?->toIso8601String(),
            ],

            // Status
            'is_anonymous' => $isAnonymous, // La conversation a été créée depuis un message anonyme
            'identity_revealed' => $canViewIdentity,
            'has_premium' => $this->has_premium ?? false,
            'unread_count' => $this->unread_count ?? 0,
            'anonymous_message_id' => $anonymousMessageId, // ID du message anonyme si la conversation vient d'un message anonyme
            'can_initiate_reveal' => $canInitiateReveal, // L'utilisateur peut-il payer pour révéler l'identité ?

            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
