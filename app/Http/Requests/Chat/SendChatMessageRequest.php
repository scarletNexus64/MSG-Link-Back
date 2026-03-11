<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'nullable|string|max:1000',
            'reply_to_id' => 'nullable|exists:anonymous_messages,id',
            'type' => 'nullable|in:text,audio,image,video,gift',
            'media' => 'nullable|file|max:20480', // 20MB max
            'voice_type' => 'nullable|in:normal,robot,alien,mystery,chipmunk',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Le message ne peut pas dépasser 1000 caractères.',
            'media.max' => 'Le fichier ne peut pas dépasser 20MB.',
        ];
    }

    /**
     * Préparer les données pour la validation
     * Décoder metadata si c'est une chaîne JSON (venant de FormData)
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true),
            ]);
        }
    }
}
