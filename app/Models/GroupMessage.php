<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Encryptable;

class GroupMessage extends Model
{
    use HasFactory, SoftDeletes, Encryptable;

    protected $fillable = [
        'group_id',
        'sender_id',
        'content',
        'type',
        'media_url',
        'metadata',
        'reply_to_message_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'sender_anonymous_name',
    ];

    /**
     * Champs à chiffrer
     */
    protected $encryptable = ['content'];

    /**
     * Types de messages
     */
    const TYPE_TEXT = 'text';
    const TYPE_IMAGE = 'image';
    const TYPE_AUDIO = 'audio';
    const TYPE_VIDEO = 'video';
    const TYPE_GIFT = 'gift';
    const TYPE_SYSTEM = 'system';

    // ==================== RELATIONS ====================

    /**
     * Groupe auquel appartient le message
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Expéditeur du message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withoutTrashed();
    }

    /**
     * Message auquel celui-ci répond
     */
    public function replyToMessage(): BelongsTo
    {
        return $this->belongsTo(GroupMessage::class, 'reply_to_message_id');
    }

    /**
     * Réponses à ce message
     */
    public function replies()
    {
        return $this->hasMany(GroupMessage::class, 'reply_to_message_id');
    }

    // ==================== ACCESSORS ====================

    /**
     * Nom anonyme de l'expéditeur
     */
    public function getSenderAnonymousNameAttribute(): string
    {
        if (!$this->sender_id || !$this->group_id) {
            return 'Anonyme';
        }

        // Récupérer le membre du groupe pour avoir son nom anonyme
        $member = GroupMember::where('group_id', $this->group_id)
            ->where('user_id', $this->sender_id)
            ->first();

        return $member ? $member->anonymous_name : 'Anonyme';
    }

    /**
     * Accessor pour le contenu (déchiffré automatiquement)
     */
    public function getContentAttribute($value): ?string
    {
        // Si pas de valeur, retourner null
        if (empty($value)) {
            return null;
        }

        // Si c'est un message système, pas de chiffrement
        if ($this->type === self::TYPE_SYSTEM) {
            return $value;
        }

        // Forcer le déchiffrement pour les messages texte
        return $this->getDecryptedAttribute('content');
    }

    /**
     * Aperçu du contenu du message (déchiffré et tronqué)
     */
    public function getContentPreviewAttribute(): string
    {
        if ($this->type === self::TYPE_IMAGE) {
            return '📷 Image';
        }

        if ($this->type === self::TYPE_AUDIO) {
            return '🎵 Audio';
        }

        if ($this->type === self::TYPE_VIDEO) {
            return '🎥 Vidéo';
        }

        if ($this->type === self::TYPE_GIFT) {
            return '🎁 Cadeau';
        }

        if ($this->type === self::TYPE_SYSTEM) {
            // Le contenu système n'est pas chiffré
            return $this->attributes['content'] ?? '';
        }

        // Pour les messages texte, forcer le déchiffrement directement
        $content = $this->getDecryptedAttribute('content') ?? '';

        if (strlen($content) > 50) {
            return substr($content, 0, 47) . '...';
        }

        return $content;
    }

    // ==================== SCOPES ====================

    /**
     * Messages d'un type spécifique
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Messages texte uniquement
     */
    public function scopeTextOnly($query)
    {
        return $query->where('type', self::TYPE_TEXT);
    }

    /**
     * Messages système uniquement
     */
    public function scopeSystemOnly($query)
    {
        return $query->where('type', self::TYPE_SYSTEM);
    }

    /**
     * Messages récents
     */
    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    // ==================== METHODS ====================

    /**
     * Créer un message système
     */
    public static function createSystemMessage(Group $group, string $content): self
    {
        return self::create([
            'group_id' => $group->id,
            'sender_id' => $group->creator_id, // Arbitraire pour système
            'content' => $content,
            'type' => self::TYPE_SYSTEM,
        ]);
    }

    /**
     * Vérifier si le message appartient à un utilisateur
     */
    public function belongsToUser(User $user): bool
    {
        return $this->sender_id === $user->id;
    }

    /**
     * Vérifier si c'est un message système
     */
    public function isSystemMessage(): bool
    {
        return $this->type === self::TYPE_SYSTEM;
    }

    /**
     * Formater pour l'API
     */
    public function toApiFormat(): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'sender_anonymous_name' => $this->sender_anonymous_name,
            'content' => $this->content,
            'type' => $this->type,
            'reply_to_message_id' => $this->reply_to_message_id,
            'created_at' => $this->created_at?->toISOString(),
            'is_own_message' => $this->sender_id === auth()->id(),
        ];
    }
}
