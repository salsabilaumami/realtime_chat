<?php

namespace App\Http\Controllers;

use App\Events\UserPresenceUpdated;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // Halaman utama chat
    public function index(Request $request)
    {
        $user = Auth::user();

        // Ambil semua conversation milik user, beserta pesan terakhir
        $conversations = $user->conversations()
            ->with(['participants', 'lastMessage.sender'])
            ->get()
            ->map(function ($conv) use ($user) {
                return [
                    'id'           => $conv->id,
                    'name'         => $conv->getNameForUser($user),
                    'type'         => $conv->type,
                    'last_message' => $conv->lastMessage?->body ?? 'Belum ada pesan',
                    'last_time'    => $conv->lastMessage?->created_at?->format('H:i') ?? '',
                    'unread'       => $conv->unreadCount($user),
                    'participants' => $conv->participants->map(fn($p) => [
                        'id'        => $p->id,
                        'name'      => $p->name,
                        'is_online' => $p->is_online,
                        'initials'  => $p->initials,
                    ]),
                ];
            });

        // Semua user lain (untuk mulai private chat baru)
        $users = User::where('id', '!=', $user->id)
            ->select('id', 'name', 'is_online', 'last_seen')
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'initials'  => $u->initials,
                'is_online' => $u->is_online,
                'last_seen' => $u->last_seen_formatted,
            ]);

        // Set user sebagai online
        $user->update(['is_online' => true, 'last_seen' => now()]);
        broadcast(new UserPresenceUpdated($user, true));

        return view('chat.index', compact('conversations', 'users', 'user'));
    }
}
