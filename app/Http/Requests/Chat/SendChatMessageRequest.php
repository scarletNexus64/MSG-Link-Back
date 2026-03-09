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
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Le message ne peut pas dépasser 1000 caractères.',
            'media.max' => 'Le fichier ne peut pas dépasser 20MB.',
        ];
    }
}
