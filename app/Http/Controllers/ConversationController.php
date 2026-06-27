<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    // Buat private chat baru ATAU ambil yang sudah ada
    public function createPrivate(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $authUser  = Auth::user();
        $otherUser = User::findOrFail($request->user_id);

        // Cari apakah sudah ada private conversation antara 2 user ini
        $existing = Conversation::where('type', 'private')
            ->whereHas('participants', fn($q) => $q->where('user_id', $authUser->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $otherUser->id))
            ->first();

        if ($existing) {
            return response()->json(['conversation_id' => $existing->id]);
        }

        // Buat conversation baru
        $conversation = Conversation::create([
            'type'       => 'private',
            'created_by' => $authUser->id,
        ]);

        // Tambahkan kedua user sebagai participant
        $conversation->participants()->attach([$authUser->id, $otherUser->id]);

        return response()->json(['conversation_id' => $conversation->id]);
    }

    // Buat group chat baru
    public function createGroup(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:100',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        $authUser = Auth::user();

        $conversation = Conversation::create([
            'name'       => $request->name,
            'type'       => 'group',
            'created_by' => $authUser->id,
        ]);

        // Tambahkan creator + semua member yang dipilih
        $memberIds = array_merge([$authUser->id], $request->member_ids);
        $conversation->participants()->attach(array_unique($memberIds));

        return response()->json([
            'conversation_id' => $conversation->id,
            'name'            => $conversation->name,
        ]);
    }
}
