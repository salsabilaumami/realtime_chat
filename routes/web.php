<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Redirect root ke chat atau login
Route::get('/', fn() => redirect()->route('chat.index'));

// Auth routes (login, register, logout) dari Breeze
require __DIR__.'/auth.php';

// Semua route chat butuh login (middleware auth)
Route::middleware(['auth'])->group(function () {

    // Halaman utama chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');

    // Conversation routes
    Route::post('/conversations/private', [ConversationController::class, 'createPrivate'])->name('conversations.private');
    Route::post('/conversations/group',   [ConversationController::class, 'createGroup'])->name('conversations.group');

    // Message routes
    Route::get('/conversations/{conversation}/messages',  [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    // Status pesan (delivered / read)
    Route::post('/conversations/{conversation}/delivered', [MessageController::class, 'markDelivered'])->name('messages.delivered');
    Route::post('/conversations/{conversation}/read',      [MessageController::class, 'markRead'])->name('messages.read');

    // Reaction emoji
    Route::post('/messages/{message}/react', [ReactionController::class, 'toggle'])->name('messages.react');

    // Presence / heartbeat routes
    Route::post('/user/heartbeat', [UserController::class, 'heartbeat'])->name('user.heartbeat');
    Route::post('/user/offline',   [UserController::class, 'setOffline'])->name('user.offline');
});
