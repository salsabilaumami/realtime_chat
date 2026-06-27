<?php

namespace App\Http\Controllers;

use App\Events\UserPresenceUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // Dipanggil saat user menutup tab / logout (set offline)
    public function setOffline(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $user->update([
                'is_online' => false,
                'last_seen' => now(),
            ]);

            broadcast(new UserPresenceUpdated($user, false));
        }

        return response()->json(['status' => 'offline']);
    }

    // Update status online (dipanggil secara berkala oleh browser)
    public function heartbeat(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $wasOffline = !$user->is_online;

            $user->update([
                'is_online' => true,
                'last_seen' => now(),
            ]);

            // Hanya broadcast jika sebelumnya offline
            if ($wasOffline) {
                broadcast(new UserPresenceUpdated($user, true));
            }
        }

        return response()->json(['status' => 'online']);
    }
}
