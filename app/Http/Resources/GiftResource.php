<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'icon' => $this->icon ?? '🎁',
            'emoji_image_url' => $this->emoji_image_url, // URL de l'image Twemoji
            'animation' => $this->animation ?? '', // Retourne string vide au lieu de null pour compatibilité frontend
            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'tier' => $this->tier,
            'tier_color' => $this->tier_color,
            'background_color' => $this->background_color ?? '#FF6B6B',
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'category' => $this->when($this->relationLoaded('category'), function () {
                return new GiftCategoryResource($this->category);
            }),
            'category_id' => $this->gift_category_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
