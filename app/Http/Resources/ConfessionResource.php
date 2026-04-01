<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfessionResource extends JsonResource
{
    /**
     * Déterminer si l'auteur doit être révélé
     * Révélé SEULEMENT si l'identité a été explicitement révélée
     */
    private function shouldRevealAuthor(Request $request): bool
    {
        // Révéler l'auteur SEULEMENT si is_identity_revealed = true
        return $this->is_identity_revealed;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'type' => $this->type,
            'is_public' => $this->is_public,
            'is_private' => $this->is_private,
            'status' => $this->status,
            'is_approved' => $this->is_approved,
            'is_pending' => $this->is_pending,

            // Média (image ou vidéo)
            'media_type' => $this->media_type ?? 'none',
            'media_url' => $this->when(
                $this->media_url && !empty(trim($this->media_url)),
                fn() => url('storage/' . $this->media_url)
            ),
            'thumbnail_url' => $this->when(
                $this->thumbnail_url && !empty(trim($this->thumbnail_url)),
                fn() => url('storage/' . $this->thumbnail_url)
            ),

            // Auteur (masqué sauf si révélé)
            'author_initial' => $this->author_initial,
            'author' => $this->when($this->shouldRevealAuthor($request), function () {
                return $this->author_info;
            }),
            'is_identity_revealed' => $this->is_identity_revealed,

            // Badge premium/certifié (visible même si anonyme)
            'is_author_premium' => $this->author && $this->author->is_premium && $this->author->premium_expires_at && $this->author->premium_expires_at->isFuture(),
            
            // Destinataire (pour confessions privées)
            'recipient' => $this->when(
                $this->is_private,
                new UserPublicResource($this->whenLoaded('recipient'))
            ),
            
            // Stats pour confessions publiques
            'likes_count' => $this->likes_count ?? 0,
            'views_count' => $this->views_count ?? 0,
            'comments_count' => $this->comments_count ?? 0,
            'is_liked' => isset($this->is_liked) ? $this->is_liked : false,

            // Est-ce ma confession ? (pour permettre la modification/suppression même si anonyme)
            'is_mine' => $request->user() && $this->author_id === $request->user()->id,

            // Statut de suppression (pour les favoris)
            'is_deleted' => $this->when(isset($this->is_deleted), $this->is_deleted),

            // Modération (admin seulement)
            $this->mergeWhen($request->user()?->is_admin || $request->user()?->is_moderator, [
                'moderated_by' => $this->whenLoaded('moderator', fn() => $this->moderator->username),
                'moderated_at' => $this->moderated_at?->toIso8601String(),
                'rejection_reason' => $this->rejection_reason,
            ]),
            
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
