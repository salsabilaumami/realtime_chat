<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        //
    }

    // Channel mana yang akan menerima event ini
    // Private channel: hanya member conversation yang bisa dengar
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    // Data apa yang dikirim ke browser
    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'body'            => $this->message->body,
            'type'            => $this->message->type,
            'media_url'       => $this->message->media_url,
            'status'          => $this->message->status,
            'created_at'      => $this->message->created_at->format('H:i'),
            'date'            => $this->message->created_at->format('d M Y'),
            'conversation_id' => $this->message->conversation_id,
            'sender'          => [
                'id'       => $this->message->sender->id,
                'name'     => $this->message->sender->name,
                'initials' => $this->message->sender->initials,
            ],
            'parent' => $this->message->parent ? [
                'id'     => $this->message->parent->id,
                'body'   => $this->message->parent->trashed() ? 'Pesan telah dihapus' : $this->message->parent->body,
                'sender_name' => $this->message->parent->sender->name,
            ] : null,
            'reactions' => [],
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
