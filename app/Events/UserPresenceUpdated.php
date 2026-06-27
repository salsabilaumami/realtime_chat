<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public bool $isOnline
    ) {
        //
    }

    // Broadcast ke public channel "presence" agar semua user bisa lihat
    public function broadcastOn(): array
    {
        return [
            new Channel('presence'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'   => $this->user->id,
            'is_online' => $this->isOnline,
            'last_seen' => $this->user->last_seen?->diffForHumans() ?? null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.presence';
    }
}
