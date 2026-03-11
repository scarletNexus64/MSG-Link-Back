<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasWallet;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasWallet;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'password',
        'original_pin',
        'avatar',
        'cover_photo',
        'bio',
        'is_verified',
        'is_premium',
        'premium_started_at',
        'premium_expires_at',
        'premium_auto_renew',
        'is_banned',
        'banned_reason',
        'banned_at',
        'wallet_balance',
        'last_seen_at',
        'settings',
        'role',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'original_pin',
        'remember_token',
        'fcm_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_verified' => 'boolean',
        'is_premium' => 'boolean',
        'premium_started_at' => 'datetime',
        'premium_expires_at' => 'datetime',
        'premium_auto_renew' => 'boolean',
        'is_banned' => 'boolean',
        'banned_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'settings' => 'array',
        'wallet_balance' => 'float',
    ];

    /**
     * Attributs par défaut
     */
    protected $attributes = [
        'settings' => '{"notifications": true, "dark_mode": "auto", "language": "fr"}',
    ];

    // ==================== ACCESSORS ====================

    /**
     * Nom complet de l'utilisateur
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Alias pour full_name (pour compatibilité)
     */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    /**
     * Initiale de l'utilisateur (pour affichage anonyme)
     */
    public function getInitialAttribute(): string
    {
        return strtoupper(substr($this->first_name, 0, 1));
    }

    /**
     * URL du profil public
     */
    public function getProfileUrlAttribute(): string
    {
        return config('app.frontend_url') . '/u/' . $this->username;
    }

    /**
     * URL de l'avatar ou avatar par défaut
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&background=random';
    }

    /**
     * URL complète de la cover photo
     */
    public function getCoverPhotoUrlAttribute(): ?string
    {
        if ($this->cover_photo) {
            return asset('storage/' . $this->cover_photo);
        }
        return null;
    }

    /**
     * Vérifier si l'utilisateur est superadmin
     */
    public function getIsSuperAdminAttribute(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Vérifier si l'utilisateur est admin (ou superadmin)
     */
    public function getIsAdminAttribute(): bool
    {
        return in_array($this->role, ['admin', 'superadmin']);
    }

    /**
     * Vérifier si l'utilisateur est modérateur, admin ou superadmin
     */
    public function getIsModeratorAttribute(): bool
    {
        return in_array($this->role, ['moderator', 'admin', 'superadmin']);
    }

    /**
     * Vérifier si l'utilisateur peut gérer un autre utilisateur
     */
    public function canManage(User $user): bool
    {
        // Un superadmin peut gérer tout le monde sauf lui-même
        if ($this->is_super_admin) {
            return $this->id !== $user->id;
        }

        // Un admin peut gérer les moderators et users, mais pas les admins/superadmins
        if ($this->role === 'admin') {
            return in_array($user->role, ['moderator', 'user']);
        }

        // Un moderator ne peut gérer que les users
        if ($this->role === 'moderator') {
            return $user->role === 'user';
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur peut bannir un autre utilisateur
     */
    public function canBan(User $user): bool
    {
        return $this->canManage($user);
    }

    /**
     * Obtenir le label du rôle
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            'superadmin' => 'Super Admin',
            'admin' => 'Administrateur',
            'moderator' => 'Modérateur',
            default => 'Utilisateur',
        };
    }

    /**
     * Vérifier si l'utilisateur est en ligne (actif dans les 5 dernières minutes)
     */
    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        return $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    /**
     * Vérifier si l'utilisateur a un passe premium actif
     */
    public function getHasActivePremiumAttribute(): bool
    {
        return $this->is_premium
            && $this->premium_expires_at
            && $this->premium_expires_at->isFuture();
    }

    /**
     * Jours restants du passe premium
     */
    public function getPremiumDaysRemainingAttribute(): int
    {
        if (!$this->has_active_premium || !$this->premium_expires_at) {
            return 0;
        }
        return (int) now()->diffInDays($this->premium_expires_at);
    }

    // ==================== RELATIONS ====================

    /**
     * Messages anonymes envoyés
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(AnonymousMessage::class, 'sender_id');
    }

    /**
     * Messages anonymes reçus
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(AnonymousMessage::class, 'recipient_id');
    }

    /**
     * Confessions écrites
     */
    public function confessionsWritten(): HasMany
    {
        return $this->hasMany(Confession::class, 'author_id');
    }

    /**
     * Confessions reçues
     */
    public function confessionsReceived(): HasMany
    {
        return $this->hasMany(Confession::class, 'recipient_id');
    }

    /**
     * Conversations (comme participant 1 ou 2)
     */
    public function conversations()
    {
        return Conversation::where('participant_one_id', $this->id)
            ->orWhere('participant_two_id', $this->id);
    }

    /**
     * Messages de chat envoyés
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    /**
     * Cadeaux envoyés
     */
    public function giftsSent(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'sender_id');
    }

    /**
     * Cadeaux reçus
     */
    public function giftsReceived(): HasMany
    {
        return $this->hasMany(GiftTransaction::class, 'recipient_id');
    }

    /**
     * Abonnements premium souscrits
     */
    public function premiumSubscriptions(): HasMany
    {
        return $this->hasMany(PremiumSubscription::class, 'subscriber_id');
    }

    /**
     * Abonnements premium dont je suis la cible
     */
    public function premiumSubscribers(): HasMany
    {
        return $this->hasMany(PremiumSubscription::class, 'target_user_id');
    }

    /**
     * Passes premium de l'utilisateur
     */
    public function premiumPasses(): HasMany
    {
        return $this->hasMany(PremiumPass::class);
    }

    /**
     * Passe premium actif
     */
    public function activePremiumPass(): HasMany
    {
        return $this->hasMany(PremiumPass::class)
            ->where('status', PremiumPass::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    /**
     * Paiements effectués
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Demandes de retrait
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * Transactions wallet
     */
    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Transactions CinetPay (dépôts, etc.)
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Utilisateurs bloqués
     */
    public function blockedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id')
            ->withTimestamps();
    }

    /**
     * Utilisateurs qui m'ont bloqué
     */
    public function blockedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocked_id', 'blocker_id')
            ->withTimestamps();
    }

    /**
     * Signalements effectués
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /**
     * Codes de vérification
     */
    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    /**
     * Stories créées
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /**
     * Stories actives
     */
    public function activeStories(): HasMany
    {
        return $this->hasMany(Story::class)
            ->where('status', Story::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    // ==================== SCOPES ====================

    /**
     * Scope pour les utilisateurs actifs (non bannis)
     */
    public function scopeActive($query)
    {
        return $query->where('is_banned', false);
    }

    /**
     * Scope pour les utilisateurs bannis
     */
    public function scopeBanned($query)
    {
        return $query->where('is_banned', true);
    }

    /**
     * Scope pour les admins
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope pour recherche
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('username', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // ==================== METHODS ====================

    /**
     * Vérifier si l'utilisateur a bloqué un autre utilisateur
     */
    public function hasBlocked(User $user): bool
    {
        return $this->blockedUsers()->where('blocked_id', $user->id)->exists();
    }

    /**
     * Vérifier si l'utilisateur est bloqué par un autre utilisateur
     */
    public function isBlockedBy(User $user): bool
    {
        return $user->hasBlocked($this);
    }

    /**
     * Vérifier si l'utilisateur a un abonnement premium actif pour une conversation
     */
    public function hasPremiumFor(Conversation $conversation): bool
    {
        return $this->premiumSubscriptions()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur a un abonnement premium actif pour un message
     */
    public function hasPremiumForMessage(AnonymousMessage $message): bool
    {
        return $this->premiumSubscriptions()
            ->where('message_id', $message->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Vérifier si l'utilisateur peut voir l'identité de tout le monde (passe premium global)
     */
    public function canViewAllIdentities(): bool
    {
        return $this->has_active_premium;
    }

    /**
     * Obtenir la conversation avec un autre utilisateur
     */
    public function getConversationWith(User $user): ?Conversation
    {
        return Conversation::between($this->id, $user->id)->first();
    }

    /**
     * Créer ou obtenir une conversation avec un autre utilisateur
     */
    public function getOrCreateConversationWith(User $user): Conversation
    {
        // Vérifier d'abord si une conversation existe (même soft deleted)
        $conversation = Conversation::withTrashed()
            ->between($this->id, $user->id)
            ->first();

        if ($conversation) {
            // Si la conversation existe mais est soft deleted, la restaurer
            if ($conversation->trashed()) {
                $conversation->restore();
                \Log::info('💬 Conversation restaurée (soft delete)', [
                    'conversation_id' => $conversation->id,
                    'participant_one_id' => $conversation->participant_one_id,
                    'participant_two_id' => $conversation->participant_two_id,
                ]);
            }

            // Révéler automatiquement la conversation pour l'utilisateur actuel s'il l'avait masquée
            if ($conversation->isHiddenFor($this)) {
                $conversation->revealFor($this);
            }
        } else {
            // Créer une nouvelle conversation si elle n'existe pas du tout
            $conversation = Conversation::create([
                'participant_one_id' => min($this->id, $user->id),
                'participant_two_id' => max($this->id, $user->id),
            ]);
            \Log::info('💬 Nouvelle conversation créée', [
                'conversation_id' => $conversation->id,
                'participant_one_id' => $conversation->participant_one_id,
                'participant_two_id' => $conversation->participant_two_id,
            ]);
        }

        return $conversation;
    }

    /**
     * Bannir l'utilisateur
     */
    public function ban(string $reason = null): void
    {
        $this->update([
            'is_banned' => true,
            'banned_reason' => $reason,
            'banned_at' => now(),
        ]);

        // Révoquer tous les tokens
        $this->tokens()->delete();
    }

    /**
     * Débannir l'utilisateur
     */
    public function unban(): void
    {
        $this->update([
            'is_banned' => false,
            'banned_reason' => null,
            'banned_at' => null,
        ]);
    }

    /**
     * Mettre à jour le dernier vu
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_seen_at' => now()]);
    }

    /**
     * Générer un username unique
     */
    public static function generateUsername(string $firstName, string $lastName): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName));
        $username = $base;
        $counter = 1;

        while (self::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }
}
