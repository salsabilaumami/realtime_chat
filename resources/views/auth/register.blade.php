@extends('layouts.app')
@section('title', 'Daftar — RealtimeChat')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="logo-icon">💬</div>
            <h1 class="logo-text">RealtimeChat</h1>
            <p class="logo-sub">Buat akun baru</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="auth-form">
            @csrf

            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                       placeholder="Nama kamu" required autofocus autocomplete="name">
                @error('name')
                    <span class="error-msg">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       placeholder="nama@email.com" required autocomplete="username">
                @error('email')
                    <span class="error-msg">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" type="password" name="password"
                       placeholder="Min. 8 karakter" required autocomplete="new-password">
                @error('password')
                    <span class="error-msg">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">Konfirmasi Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       placeholder="Ulangi password" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary">Buat Akun</button>
        </form>

        <p class="auth-switch">
            Sudah punya akun?
            <a href="{{ route('login') }}">Masuk di sini</a>
        </p>
    </div>
</div>
@endsection
