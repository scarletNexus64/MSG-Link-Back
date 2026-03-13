<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $groupId;
    public int $messageId;

    public function __construct(int $groupId, int $messageId)
    {
        $this->groupId = $groupId;
        $this->messageId = $messageId;
    }

    public function broadcastOn(): array
    {
        \Log::info('🗑️ [EVENT] GroupMessageDeleted broadcasting', [
            'message_id' => $this->messageId,
            'group_id' => $this->groupId,
        ]);

        return [
            new PrivateChannel('group.' . $this->groupId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'group_id' => $this->groupId,
        ];
    }
}
