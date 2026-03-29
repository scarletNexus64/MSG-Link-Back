<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'emoji_image_path',
        'animation',
        'price',
        'tier',
        'sort_order',
        'is_active',
        'gift_category_id',
        'background_color',
    ];

    protected $casts = [
        'price' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Niveaux de cadeaux
     */
    const TIER_BRONZE = 'bronze';
    const TIER_SILVER = 'silver';
    const TIER_GOLD = 'gold';
    const TIER_DIAMOND = 'diamond';

    /**
     * Prix par défaut selon le cahier des charges
     */
    const DEFAULT_PRICES = [
        self::TIER_BRONZE => 1000,    // Cœur - 1 000 FCFA
        self::TIER_SILVER => 5000,    // Chocolat - 5 000 FCFA
        self::TIER_GOLD => 25000,     // Bouquet - 25 000 FCFA
        self::TIER_DIAMOND => 50000,  // Bague - 50 000 FCFA
    ];

    // ==================== RELATIONS ====================

    /**
     * Transactions de ce cadeau
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(GiftTransaction::class);
    }

    /**
     * Catégorie du cadeau
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GiftCategory::class, 'gift_category_id');
    }

    // ==================== ACCESSORS ====================

    /**
     * Prix formaté
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Couleur du tier
     */
    public function getTierColorAttribute(): string
    {
        return match ($this->tier) {
            self::TIER_BRONZE => '#CD7F32',
            self::TIER_SILVER => '#C0C0C0',
            self::TIER_GOLD => '#FFD700',
            self::TIER_DIAMOND => '#B9F2FF',
            default => '#666666',
        };
    }

    /**
     * URL de l'image emoji (Twemoji)
     */
    public function getEmojiImageUrlAttribute(): ?string
    {
        if (!$this->emoji_image_path) {
            return null;
        }

        return asset('storage/' . $this->emoji_image_path);
    }

    // ==================== SCOPES ====================

    /**
     * Cadeaux actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Cadeaux par tier
     */
    public function scopeByTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }

    /**
     * Triés par ordre
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }
}
