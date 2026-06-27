<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

// Private channel untuk setiap conversation
// Hanya member conversation yang boleh subscribe
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (!$conversation) {
        return false;
    }

    return $conversation->participants()->where('user_id', $user->id)->exists();
});

// Public channel untuk presence tracking (siapa online/offline)
Broadcast::channel('presence', function ($user) {
    return true; // semua user yang login boleh dengar
});
