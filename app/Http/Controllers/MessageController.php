<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    // Ambil history pesan di sebuah conversation
    public function index(Conversation $conversation)
    {
        $user = Auth::user();

        // Pastikan user adalah member conversation ini
        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403, 'Anda bukan anggota percakapan ini.'
        );

        $messages = $conversation->messages()
            ->withTrashed()
            ->with(['sender', 'parent.sender', 'reactions.user:id,name'])
            ->reorder('created_at', 'asc')
            ->get()
            ->map(fn ($msg) => $this->formatMessage($msg, $user));

        // Update last_read_at untuk user ini
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);

        return response()->json($messages);
    }

    // Kirim pesan baru (teks dan/atau gambar)
    public function store(Request $request, Conversation $conversation)
    {
        $user = Auth::user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403, 'Anda bukan anggota percakapan ini.'
        );

        $request->validate([
            'body'       => 'nullable|string|max:5000',
            'image'      => 'nullable|image|max:5120', // max 5MB
            'parent_id'  => 'nullable|exists:messages,id',
        ]);

        if (!$request->filled('body') && !$request->hasFile('image')) {
            return response()->json(['message' => 'Pesan kosong, tulis sesuatu atau lampirkan gambar.'], 422);
        }

        $mediaPath = null;
        $type = 'text';

        if ($request->hasFile('image')) {
            // Disimpan di storage/app/public/chat-images, diakses lewat /storage/chat-images/...
            $mediaPath = $request->file('image')->store('chat-images', 'public');
            $type = 'image';
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'body'            => $request->input('body'),
            'type'            => $type,
            'media_path'      => $mediaPath,
            'parent_id'       => $request->input('parent_id'),
            'status'          => 'sent',
        ]);

        $message->load(['sender', 'parent.sender']);

        // Update waktu conversation (agar muncul di atas list)
        $conversation->touch();

        // Broadcast ke semua user di conversation ini via WebSocket
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($this->formatMessage($message, $user, true));
    }

    // Hapus pesan (soft delete, cuma pengirim yang boleh hapus)
    public function destroy(Message $message)
    {
        $user = Auth::user();

        abort_unless($message->user_id === $user->id, 403, 'Kamu hanya bisa menghapus pesanmu sendiri.');

        $message->delete(); // soft delete

        broadcast(new MessageDeleted($message))->toOthers();

        return response()->json(['status' => 'deleted', 'id' => $message->id]);
    }

    // Tandai semua pesan di conversation ini (yang bukan milik sendiri) sebagai "delivered"
    public function markDelivered(Conversation $conversation)
    {
        $user = Auth::user();

        $ids = $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->where('status', 'sent')
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $conversation->messages()->whereIn('id', $ids)->update(['status' => 'delivered']);
            broadcast(new MessageStatusUpdated($conversation->id, $ids->toArray(), 'delivered'))->toOthers();
        }

        return response()->json(['status' => 'ok']);
    }

    // Tandai semua pesan di conversation ini (yang bukan milik sendiri) sebagai "read"
    public function markRead(Conversation $conversation)
    {
        $user = Auth::user();

        $ids = $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->whereIn('status', ['sent', 'delivered'])
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $conversation->messages()->whereIn('id', $ids)->update(['status' => 'read']);
            broadcast(new MessageStatusUpdated($conversation->id, $ids->toArray(), 'read'))->toOthers();
        }

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    // Helper: format 1 message jadi array response yang konsisten dipakai index() & store()
    private function formatMessage(Message $msg, $user, bool $isMine = null): array
    {
        return [
            'id'         => $msg->id,
            'body'       => $msg->trashed() ? null : $msg->body,
            'is_deleted' => $msg->trashed(),
            'type'       => $msg->type,
            'media_url'  => $msg->trashed() ? null : $msg->media_url,
            'status'     => $msg->status,
            'created_at' => $msg->created_at->format('H:i'),
            'date'       => $msg->created_at->format('d M Y'),
            'is_mine'    => $isMine ?? ($msg->user_id === $user->id),
            'sender'     => [
                'id'       => $msg->sender->id,
                'name'     => $msg->sender->name,
                'initials' => $msg->sender->initials,
            ],
            'parent' => $msg->parent ? [
                'id'          => $msg->parent->id,
                'body'        => $msg->parent->trashed() ? 'Pesan telah dihapus' : $msg->parent->body,
                'sender_name' => $msg->parent->sender->name,
            ] : null,
            'reactions' => $msg->reactions->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name'    => $r->user->name,
                'emoji'   => $r->emoji,
            ]),
        ];
    }
}
