<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'creator_id',
        'invite_code',
        'is_public',
        'is_discoverable',
        'max_members',
        'members_count',
        'messages_count',
        'last_message_at',
        'avatar_url',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_discoverable' => 'boolean',
        'max_members' => 'integer',
        'members_count' => 'integer',
        'messages_count' => 'integer',
        'last_message_at' => 'datetime',
    ];

    protected $appends = [
        'invite_link',
    ];

    // ==================== CONSTANTS ====================

    const MAX_MEMBERS_DEFAULT = 50;
    const MAX_MEMBERS_PREMIUM = 200;

    // ==================== RELATIONS ====================

    /**
     * Catégorie du groupe
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GroupCategory::class, 'category_id');
    }

    /**
     * Créateur du groupe
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withoutTrashed();
    }

    /**
     * Membres du groupe
     */
    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    /**
     * Membres actifs (non supprimés)
     */
    public function activeMembers(): HasMany
    {
        return $this->hasMany(GroupMember::class)->whereNull('deleted_at');
    }

    /**
     * Messages du groupe
     */
    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class)->orderBy('created_at', 'asc');
    }

    /**
     * Dernier message
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(GroupMessage::class)->latestOfMany();
    }

    // ==================== ACCESSORS ====================

    /**
     * Lien d'invitation complet
     */
    public function getInviteLinkAttribute(): string
    {
        $appUrl = config('app.frontend_url', 'http://localhost:5173');
        return "{$appUrl}/groups/join/{$this->invite_code}";
    }

    // ==================== SCOPES ====================

    /**
     * Groupes publics
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Groupes avec activité récente
     */
    public function scopeWithRecentActivity($query)
    {
        return $query->whereNotNull('last_message_at')
            ->orderBy('last_message_at', 'desc');
    }

    /**
     * Recherche par nom ou description
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Filtrer par catégorie
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // ==================== METHODS ====================

    /**
     * Générer un code d'invitation unique
     */
    public static function generateInviteCode(): string
    {
        do {
            $code = Str::random(8);
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * Vérifier si un utilisateur est membre
     */
    public function hasMember(User $user): bool
    {
        return $this->activeMembers()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Vérifier si un utilisateur est le créateur
     */
    public function isCreator(User $user): bool
    {
        return $this->creator_id === $user->id;
    }

    /**
     * Vérifier si un utilisateur est admin
     */
    public function isAdmin(User $user): bool
    {
        return $this->activeMembers()
            ->where('user_id', $user->id)
            ->where('role', GroupMember::ROLE_ADMIN)
            ->exists();
    }

    /**
     * Vérifier si le groupe peut accepter plus de membres
     */
    public function canAcceptMoreMembers(): bool
    {
        return $this->members_count < $this->max_members;
    }

    /**
     * Ajouter un membre au groupe
     */
    public function addMember(User $user, string $role = GroupMember::ROLE_MEMBER): ?GroupMember
    {
        // Vérifier si l'utilisateur est déjà membre
        if ($this->hasMember($user)) {
            return null;
        }

        // Vérifier la limite de membres
        if (!$this->canAcceptMoreMembers()) {
            return null;
        }

        // Créer le membre
        $member = $this->members()->create([
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);

        // Incrémenter le compteur
        $this->increment('members_count');

        // Message système anonyme
        GroupMessage::createSystemMessage($this, "Anonyme a rejoint le groupe");

        return $member;
    }

    /**
     * Retirer un membre du groupe
     */
    public function removeMember(User $user): bool
    {
        $member = $this->activeMembers()
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return false;
        }

        // Récupérer le nom anonyme avant de supprimer
        $anonymousName = $member->anonymous_name;
        $member->delete();
        $this->decrement('members_count');

        // Message système avec nom anonyme
        GroupMessage::createSystemMessage($this, "{$anonymousName} a quitté le groupe");

        return true;
    }

    /**
     * Mettre à jour après un nouveau message
     */
    public function updateAfterMessage(): void
    {
        $this->increment('messages_count');
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Obtenir les membres non lus pour un message
     */
    public function getMembersExcept(User $user)
    {
        return $this->activeMembers()
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get();
    }

    /**
     * Compter les messages non lus pour un utilisateur
     */
    public function unreadCountFor(User $user): int
    {
        $member = $this->activeMembers()
            ->where('user_id', $user->id)
            ->first();

        if (!$member) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('created_at', '>', $member->last_read_at ?? $member->joined_at)
            ->count();
    }

    /**
     * Régénérer le code d'invitation
     */
    public function regenerateInviteCode(): void
    {
        $this->update([
            'invite_code' => self::generateInviteCode(),
        ]);
    }
}
