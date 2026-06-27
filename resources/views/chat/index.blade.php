@extends('layouts.app')
@section('title', 'Chat — RealtimeChat')

@section('content')
<div class="chat-app" id="chat-app"
    data-user="{{ json_encode(['id' => $user->id, 'name' => $user->name, 'initials' => $user->initials]) }}"
    data-conversations="{{ json_encode($conversations) }}"
    data-users="{{ json_encode($users) }}">

    {{-- ===== SIDEBAR KIRI ===== --}}
    <aside class="sidebar" id="sidebar">

        {{-- Header Sidebar --}}
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <span class="brand-icon">💬</span>
                <span class="brand-name">RealtimeChat</span>
            </div>
            <div class="header-actions">
                <button class="icon-btn" id="btn-new-group" title="Buat Group Chat">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </button>
                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                    @csrf
                    <button type="submit" class="icon-btn" title="Logout">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </form>
            </div>
        </div>

        {{-- Info User Login --}}
        <div class="current-user">
            <div class="avatar avatar-sm online">{{ $user->initials }}</div>
            <div class="current-user-info">
                <span class="current-user-name">{{ $user->name }}</span>
                <span class="current-user-status">● Online</span>
            </div>
        </div>

        {{-- Search --}}
        <div class="search-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="search-input" placeholder="Cari percakapan atau user...">
        </div>

        {{-- Tab: Chats | Users --}}
        <div class="sidebar-tabs">
            <button class="tab-btn active" data-tab="chats" id="tab-chats">Chats</button>
            <button class="tab-btn" data-tab="users" id="tab-users">Semua User</button>
        </div>

        {{-- List Conversations --}}
        <div class="sidebar-tab-content active" id="tab-content-chats">
            <div class="conv-list" id="conversation-list">
                <div class="empty-list" id="empty-chats">
                    <span>Belum ada percakapan.<br>Klik "Semua User" untuk mulai chat.</span>
                </div>
            </div>
        </div>

        {{-- List All Users --}}
        <div class="sidebar-tab-content" id="tab-content-users">
            <div class="users-list" id="users-list"></div>
        </div>
    </aside>

    {{-- ===== AREA CHAT UTAMA ===== --}}
    <main class="chat-main" id="chat-main">

        {{-- Welcome Screen (belum pilih conversation) --}}
        <div class="chat-welcome" id="chat-welcome">
            <div class="welcome-content">
                <div class="welcome-icon">💬</div>
                <h2>Selamat datang di RealtimeChat</h2>
                <p>Pilih percakapan di sebelah kiri atau mulai chat baru dengan memilih user.</p>
            </div>
        </div>

        {{-- Chat Window --}}
        <div class="chat-window hidden" id="chat-window">

            {{-- Header Chat --}}
            <div class="chat-header" id="chat-header">
                <div class="chat-header-info">
                    <div class="avatar" id="chat-avatar">?</div>
                    <div>
                        <div class="chat-name" id="chat-name">-</div>
                        <div class="chat-status" id="chat-status">-</div>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <span class="chat-type-badge" id="chat-type-badge"></span>
                </div>
            </div>

            {{-- Messages Area --}}
            <div class="messages-area" id="messages-area">
                <div class="messages-loading" id="messages-loading">
                    <div class="spinner"></div>
                    <span>Memuat pesan...</span>
                </div>
                <div class="messages-list" id="messages-list"></div>
            </div>

            {{-- Input Box --}}
            <div class="chat-input-area">

                {{-- Reply preview bar --}}
                <div class="reply-bar hidden" id="reply-bar">
                    <div class="reply-bar-content">
                        <span class="reply-bar-label">Membalas <span id="reply-bar-name"></span></span>
                        <span class="reply-bar-text" id="reply-bar-text"></span>
                    </div>
                    <button type="button" class="modal-close" id="reply-bar-close">✕</button>
                </div>

                {{-- Image preview bar --}}
                <div class="image-preview-bar hidden" id="image-preview-bar">
                    <img id="image-preview-img" alt="preview">
                    <button type="button" class="modal-close" id="image-preview-remove">✕</button>
                </div>

                <form class="chat-input-form" id="message-form">
                    @csrf
                    <button type="button" class="icon-btn" id="btn-attach" title="Kirim gambar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    </button>
                    <input type="file" id="file-input" accept="image/*" class="hidden">
                    <input type="text" id="message-input"
                           placeholder="Ketik pesan..." autocomplete="off" maxlength="5000">
                    <button type="submit" class="send-btn" id="send-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>

{{-- ===== MODAL: Buat Group Chat ===== --}}
<div class="modal-overlay hidden" id="modal-group">
    <div class="modal">
        <div class="modal-header">
            <h3>Buat Group Chat</h3>
            <button class="modal-close" id="modal-group-close">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nama Group</label>
                <input type="text" id="group-name-input" placeholder="Contoh: Tim Developer">
            </div>
            <div class="form-group">
                <label>Pilih Anggota</label>
                <div class="member-list" id="member-list"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" id="modal-group-cancel">Batal</button>
            <button class="btn-primary" id="btn-create-group">Buat Group</button>
        </div>
    </div>
</div>
@endsection
