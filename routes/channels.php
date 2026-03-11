<?php

use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Canal privé pour les notifications utilisateur
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

/**
 * Canal privé pour une conversation
 */
Broadcast::channel('conversation.{conversationId}', function (User $user, int $conversationId) {
    \Log::info('🔐 Channel auth attempt', [
        'user_id' => $user->id,
        'user_name' => $user->username,
        'conversation_id' => $conversationId
    ]);

    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        \Log::warning('❌ Conversation not found', ['id' => $conversationId]);
        return false;
    }

    \Log::info('📋 Conversation found', [
        'conversation_id' => $conversationId,
        'participant_one_id' => $conversation->participant_one_id,
        'participant_two_id' => $conversation->participant_two_id
    ]);

    $hasAccess = $conversation->hasParticipant($user);
    \Log::info('✅ hasParticipant result', [
        'conversation_id' => $conversationId,
        'user_id' => $user->id,
        'has_access' => $hasAccess
    ]);

    return $hasAccess;
});

/**
 * Canal de présence pour voir qui est en ligne
 */
Broadcast::channel('presence.online', function (User $user) {
    return [
        'id' => $user->id,
        'username' => $user->username,
        'initial' => $user->initial,
    ];
});

/**
 * Canal privé pour un groupe
 */
Broadcast::channel('group.{groupId}', function (User $user, int $groupId) {
    $group = Group::find($groupId);

    if (!$group) {
        return false;
    }

    return $group->hasMember($user);
});
