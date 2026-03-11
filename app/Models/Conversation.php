<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'participant_one_id',
        'participant_two_id',
        'pinned_anonymous_message_id',
        'last_message_at',
        'streak_count',
        'streak_updated_at',
        'flame_level',
        'message_count',
        'is_hidden_by_participant_one',
        'hidden_by_participant_one_at',
        'is_hidden_by_participant_two',
        'hidden_by_participant_two_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'streak_updated_at' => 'datetime',
        'hidden_by_participant_one_at' => 'datetime',
        'hidden_by_participant_two_at' => 'datetime',
        'is_hidden_by_participant_one' => 'boolean',
        'is_hidden_by_participant_two' => 'boolean',
        'streak_count' => 'integer',
        'message_count' => 'integer',
    ];

    /**
     * Niveaux de flame
     */
    const FLAME_NONE = 'none';
    const FLAME_YELLOW = 'yellow';      // 2 jours
    const FLAME_ORANGE = 'orange';      // 7 jours
    const FLAME_PURPLE = 'purple';      // 30 jours

    // ==================== RELATIONS ====================

    /**
     * Premier participant
     */
    public function participantOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_one_id')->withoutTrashed();
    }

    /**
     * Second participant
     */
    public function participantTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_two_id')->withoutTrashed();
    }

    /**
     * Messages de la conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Dernier message
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /**
     * Abonnements premium associés
     */
    public function premiumSubscriptions(): HasMany
    {
        return $this->hasMany(PremiumSubscription::class);
    }

    /**
     * Transactions de cadeaux
     */
    public function giftTransactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class);
    }

    /**
     * Révélations d'identité dans cette conversation
     */
    public function identityReveals(): HasMany
    {
        return $this->hasMany(ConversationIdentityReveal::class);
    }

    /**
     * Message anonyme épinglé (contexte de la conversation)
     */
    public function pinnedAnonymousMessage(): BelongsTo
    {
        return $this->belongsTo(AnonymousMessage::class, 'pinned_anonymous_message_id');
    }

    // ==================== ACCESSORS ====================

    /**
     * Emoji de la flame selon le niveau
     */
    public function getFlameEmojiAttribute(): string
    {
        return match ($this->flame_level) {
            self::FLAME_YELLOW => '🔥',
            self::FLAME_ORANGE => '🔥',
            self::FLAME_PURPLE => '💜🔥',
            default => '',
        };
    }

    /**
     * Couleur de la flame pour le frontend
     */
    public function getFlameColorAttribute(): ?string
    {
        return match ($this->flame_level) {
            self::FLAME_YELLOW => '#FFD700',
            self::FLAME_ORANGE => '#FF6B35',
            self::FLAME_PURPLE => '#9B59B6',
            default => null,
        };
    }

    // ==================== SCOPES ====================

    /**
     * Conversations entre deux utilisateurs
     */
    public function scopeBetween($query, int $userId1, int $userId2)
    {
        $minId = min($userId1, $userId2);
        $maxId = max($userId1, $userId2);

        return $query->where('participant_one_id', $minId)
            ->where('participant_two_id', $maxId);
    }

    /**
     * Conversations d'un utilisateur (exclut celles masquées)
     * Simple et performant avec index sur is_hidden
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where(function ($subQ) use ($userId) {
                // Si l'utilisateur est participant_one et n'a pas masqué
                $subQ->where('participant_one_id', $userId)
                    ->where('is_hidden_by_participant_one', false);
            })->orWhere(function ($subQ) use ($userId) {
                // Si l'utilisateur est participant_two et n'a pas masqué
                $subQ->where('participant_two_id', $userId)
                    ->where('is_hidden_by_participant_two', false);
            });
        });
    }

    /**
     * Conversations avec des messages récents
     */
    public function scopeWithRecentActivity($query)
    {
        return $query->whereNotNull('last_message_at')
            ->orderBy('last_message_at', 'desc');
    }

    /**
     * Conversations avec streak actif
     */
    public function scopeWithStreak($query)
    {
        return $query->where('streak_count', '>', 0);
    }

    // ==================== METHODS ====================

    /**
     * Obtenir l'autre participant
     */
    public function getOtherParticipant(User $user): ?User
    {
        return $this->participant_one_id === $user->id
            ? $this->participantTwo
            : $this->participantOne;
    }

    /**
     * Vérifier si un utilisateur est participant
     */
    public function hasParticipant(User $user): bool
    {
        return $this->participant_one_id === $user->id
            || $this->participant_two_id === $user->id;
    }

    /**
     * Mettre à jour après un nouveau message
     */
    public function updateAfterMessage(): void
    {
        $this->increment('message_count');
        $this->update(['last_message_at' => now()]);
        $this->updateStreak();
    }

    /**
     * Mettre à jour le streak
     * OPTIMISÉ : Ne fait les requêtes lourdes que max 1 fois par jour
     */
    public function updateStreak(): void
    {
        $now = now();

        // OPTIMISATION : Vérifier si on a déjà mis à jour le streak aujourd'hui
        // Si oui, on skip complètement pour éviter les requêtes SQL inutiles
        if ($this->streak_updated_at && $this->streak_updated_at->isToday()) {
            // Déjà mis à jour aujourd'hui, on ne fait rien
            return;
        }

        $todayStart = $now->copy()->startOfDay();

        // OPTIMISATION : Une seule requête pour vérifier les deux participants
        // au lieu de 2 requêtes séparées
        // Utiliser DB::table directement pour éviter les conflits avec orderBy de la relation
        $messagesToday = \DB::table('chat_messages')
            ->where('conversation_id', $this->id)
            ->whereIn('sender_id', [$this->participant_one_id, $this->participant_two_id])
            ->where('created_at', '>=', $todayStart)
            ->whereNull('deleted_at')
            ->select('sender_id')
            ->distinct()
            ->pluck('sender_id')
            ->toArray();

        $participantOneMessaged = in_array($this->participant_one_id, $messagesToday);
        $participantTwoMessaged = in_array($this->participant_two_id, $messagesToday);

        // Si pas de streak précédent, initialiser
        if (!$this->streak_updated_at) {
            if ($participantOneMessaged && $participantTwoMessaged) {
                $this->increment('streak_count');
                $this->update([
                    'streak_updated_at' => $now,
                    'flame_level' => $this->calculateFlameLevel($this->streak_count + 1),
                ]);
            }
            return;
        }

        // Calculer le nombre d'heures depuis la dernière mise à jour
        $hoursSinceUpdate = $this->streak_updated_at->diffInHours($now);

        // Si plus de 24h depuis la dernière mise à jour
        if ($hoursSinceUpdate > 24) {
            // Calculer combien de jours se sont écoulés
            $daysMissed = floor($hoursSinceUpdate / 24);

            // Si les deux ont messagé aujourd'hui, on incrémente
            if ($participantOneMessaged && $participantTwoMessaged) {
                // Décrémenter d'abord pour les jours manqués (sauf aujourd'hui)
                $newStreakCount = max(0, $this->streak_count - ($daysMissed - 1));

                // Puis incrémenter pour aujourd'hui
                $newStreakCount++;

                $this->update([
                    'streak_count' => $newStreakCount,
                    'streak_updated_at' => $now,
                    'flame_level' => $this->calculateFlameLevel($newStreakCount),
                ]);
            } else {
                // Aucun des deux n'a messagé aujourd'hui, décrémenter pour tous les jours manqués
                $newStreakCount = max(0, $this->streak_count - $daysMissed);

                $this->update([
                    'streak_count' => $newStreakCount,
                    'streak_updated_at' => $now,
                    'flame_level' => $this->calculateFlameLevel($newStreakCount),
                ]);
            }
        }
    }

    /**
     * Calculer le niveau de flame
     */
    protected function calculateFlameLevel(int $streakCount): string
    {
        return match (true) {
            $streakCount >= 30 => self::FLAME_PURPLE,
            $streakCount >= 7 => self::FLAME_ORANGE,
            $streakCount >= 2 => self::FLAME_YELLOW,
            default => self::FLAME_NONE,
        };
    }

    /**
     * Réinitialiser le streak (appelé si le streak expire)
     */
    public function resetStreak(): void
    {
        $this->update([
            'streak_count' => 0,
            'flame_level' => self::FLAME_NONE,
            'streak_updated_at' => null,
        ]);
    }

    /**
     * Vérifier si un utilisateur a un abonnement premium actif
     */
    public function hasPremiumSubscription(User $user): bool
    {
        return $this->premiumSubscriptions()
            ->where('subscriber_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Compter les messages non lus pour un utilisateur
     * Ne compte que les messages après le timestamp de masquage si applicable
     */
    public function unreadCountFor(User $user): int
    {
        $query = $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false);

        // Ne compter que les messages après le masquage
        $hiddenAt = $this->getHiddenAtFor($user);
        if ($hiddenAt) {
            $query->where('created_at', '>', $hiddenAt);
        }

        return $query->count();
    }

    /**
     * Marquer tous les messages comme lus pour un utilisateur
     * Ne marque que les messages après le timestamp de masquage si applicable
     */
    public function markAllAsReadFor(User $user): void
    {
        $query = $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false);

        // Ne marquer que les messages après le masquage
        $hiddenAt = $this->getHiddenAtFor($user);
        if ($hiddenAt) {
            $query->where('created_at', '>', $hiddenAt);
        }

        $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Vérifier si un utilisateur a révélé l'identité de l'autre participant
     */
    public function hasRevealedIdentityFor(User $user, User $otherUser): bool
    {
        return $this->identityReveals()
            ->where('user_id', $user->id)
            ->where('revealed_user_id', $otherUser->id)
            ->exists();
    }

    /**
     * Vérifier si l'identité est révélée pour cet utilisateur
     * (soit il a payé dans Chat, soit il est premium, soit il a révélé un message de cette personne)
     */
    public function isIdentityRevealedFor(User $user): bool
    {
        $otherUser = $this->getOtherParticipant($user);

        // Si l'utilisateur est premium, il voit tout
        if ($user->is_premium) {
            return true;
        }

        // Vérifier si l'utilisateur a payé pour révéler l'identité dans le chat
        if ($this->hasRevealedIdentityFor($user, $otherUser)) {
            return true;
        }

        // Vérifier aussi si un message anonyme de cet utilisateur a été révélé (synchronisation Messages ↔ Chat)
        $hasRevealedMessage = \App\Models\AnonymousMessage::where('recipient_id', $user->id)
            ->where('sender_id', $otherUser->id)
            ->where('is_identity_revealed', true)
            ->exists();

        return $hasRevealedMessage;
    }

    /**
     * Obtenir le timestamp de masquage pour un utilisateur
     */
    public function getHiddenAtFor(User $user): ?\Carbon\Carbon
    {
        if ($this->participant_one_id === $user->id) {
            return $this->hidden_by_participant_one_at;
        }

        if ($this->participant_two_id === $user->id) {
            return $this->hidden_by_participant_two_at;
        }

        return null;
    }

    /**
     * Vérifier si la conversation est masquée pour un utilisateur
     */
    public function isHiddenFor(User $user): bool
    {
        if ($this->participant_one_id === $user->id) {
            return $this->is_hidden_by_participant_one;
        }

        if ($this->participant_two_id === $user->id) {
            return $this->is_hidden_by_participant_two;
        }

        return false;
    }

    /**
     * Masquer la conversation pour un utilisateur
     * Le timestamp sert de référence pour filtrer les anciens messages
     */
    public function hideFor(User $user): void
    {
        $now = now();

        if ($this->participant_one_id === $user->id) {
            $this->update([
                'is_hidden_by_participant_one' => true,
                'hidden_by_participant_one_at' => $now,
            ]);
        } elseif ($this->participant_two_id === $user->id) {
            $this->update([
                'is_hidden_by_participant_two' => true,
                'hidden_by_participant_two_at' => $now,
            ]);
        }

        \Log::info('💬 Conversation masquée', [
            'conversation_id' => $this->id,
            'user_id' => $user->id,
            'hidden_at' => $now,
        ]);
    }

    /**
     * Révéler la conversation pour un utilisateur (quand il écrit/reçoit un nouveau message)
     * Le timestamp de masquage reste pour filtrer les anciens messages
     */
    public function revealFor(User $user): void
    {
        if ($this->participant_one_id === $user->id && $this->is_hidden_by_participant_one) {
            $this->update(['is_hidden_by_participant_one' => false]);

            \Log::info('💬 Conversation révélée', [
                'conversation_id' => $this->id,
                'user_id' => $user->id,
                'hidden_at_timestamp_kept' => $this->hidden_by_participant_one_at,
            ]);
        } elseif ($this->participant_two_id === $user->id && $this->is_hidden_by_participant_two) {
            $this->update(['is_hidden_by_participant_two' => false]);

            \Log::info('💬 Conversation révélée', [
                'conversation_id' => $this->id,
                'user_id' => $user->id,
                'hidden_at_timestamp_kept' => $this->hidden_by_participant_two_at,
            ]);
        }
    }
}
