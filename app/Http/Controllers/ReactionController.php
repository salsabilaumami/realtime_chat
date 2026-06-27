<?php

namespace App\Http\Controllers;

use App\Events\ReactionUpdated;
use App\Models\Message;
use App\Models\MessageReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReactionController extends Controller
{
    // Toggle reaction: kalau user sudah kasih emoji yang sama -> dihapus (un-react)
    // kalau beda emoji -> diganti, kalau belum ada -> ditambah
    public function toggle(Request $request, Message $message)
    {
        $user = Auth::user();

        $request->validate(['emoji' => 'required|string|max:8']);

        abort_unless(
            $message->conversation->participants()->where('user_id', $user->id)->exists(),
            403, 'Anda bukan anggota percakapan ini.'
        );

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->emoji === $request->emoji) {
            // Klik emoji yang sama dua kali = un-react
            $existing->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $user->id],
                ['emoji' => $request->emoji]
            );
        }

        $message->load('reactions.user:id,name');

        broadcast(new ReactionUpdated($message))->toOthers();

        return response()->json([
            'message_id' => $message->id,
            'reactions'  => $message->reactions->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name'    => $r->user->name,
                'emoji'   => $r->emoji,
            ]),
        ]);
    }
}
