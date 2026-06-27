<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['conversation_id', 'user_id', 'body', 'type', 'parent_id', 'media_path', 'status', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    // Relasi: Message dikirim oleh User
    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi: Message ada di Conversation
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    // Relasi: pesan yang di-reply (parent) — pakai withTrashed supaya kalau parent-nya
    // sudah dihapus, kita masih bisa tampilkan label "Pesan telah dihapus" di kutipan
    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id')->withTrashed();
    }

    // Relasi: semua reaction emoji di pesan ini
    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    // Helper: apakah pesan ini dikirim oleh user tertentu
    public function isSentBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    // Helper: URL publik untuk media (gambar/voice note)
    public function getMediaUrlAttribute(): ?string
    {
        return $this->media_path ? asset('storage/' . $this->media_path) : null;
    }
}

