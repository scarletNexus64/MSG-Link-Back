<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupReport extends Model
{
    protected $fillable = [
        'group_id',
        'reporter_id',
        'reason',
        'description',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // ==================== CONSTANTS ====================

    // Raisons de signalement
    const REASON_SPAM = 'spam';
    const REASON_HARASSMENT = 'harassment';
    const REASON_INAPPROPRIATE_CONTENT = 'inappropriate_content';
    const REASON_HATE_SPEECH = 'hate_speech';
    const REASON_VIOLENCE = 'violence';
    const REASON_OTHER = 'other';

    // Statuts
    const STATUS_PENDING = 'pending';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_DISMISSED = 'dismissed';

    // ==================== RELATIONS ====================

    /**
     * Groupe signalé
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Utilisateur qui a signalé
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Admin qui a traité le signalement
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ==================== SCOPES ====================

    /**
     * Signalements en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Signalements traités
     */
    public function scopeReviewed($query)
    {
        return $query->whereIn('status', [self::STATUS_REVIEWED, self::STATUS_RESOLVED, self::STATUS_DISMISSED]);
    }

    // ==================== HELPERS ====================

    /**
     * Liste des raisons de signalement
     */
    public static function getReasons(): array
    {
        return [
            self::REASON_SPAM => 'Spam',
            self::REASON_HARASSMENT => 'Harcèlement',
            self::REASON_INAPPROPRIATE_CONTENT => 'Contenu inapproprié',
            self::REASON_HATE_SPEECH => 'Discours haineux',
            self::REASON_VIOLENCE => 'Violence',
            self::REASON_OTHER => 'Autre',
        ];
    }

    /**
     * Marquer comme traité
     */
    public function markAsReviewed(User $admin, string $status, ?string $notes = null): void
    {
        $this->update([
            'status' => $status,
            'admin_notes' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }
}
