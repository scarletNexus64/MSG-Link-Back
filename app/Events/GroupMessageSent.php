<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public GroupMessage $message;

    public function __construct(GroupMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        $channels = [
            // Canal du groupe spécifique (pour les utilisateurs dans la vue du groupe)
            new PrivateChannel('group.' . $this->message->group_id),
        ];

        // Ajouter un canal pour chaque membre du groupe (pour les badges et notifications globales)
        // On récupère les membres actifs du groupe sauf l'expéditeur
        $members = $this->message->group->activeMembers()
            ->where('user_id', '!=', $this->message->sender_id)
            ->pluck('user_id');

        foreach ($members as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }

        \Log::info('🔊 [EVENT] GroupMessageSent broadcasting', [
            'message_id' => $this->message->id,
            'group_id' => $this->message->group_id,
            'sender_id' => $this->message->sender_id,
            'members_notified' => $members->count(),
            'channels_count' => count($channels),
        ]);

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'group_id' => $this->message->group_id,
            'sender_id' => $this->message->sender_id,
            'sender_anonymous_name' => $this->message->sender_anonymous_name,
            'content' => $this->message->content,
            'type' => $this->message->type,
            'media_url' => $this->message->media_url,
            'metadata' => $this->message->metadata,
            'reply_to_message_id' => $this->message->reply_to_message_id,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
