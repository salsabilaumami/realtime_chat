<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'created_by'];

    // Relasi: Conversation punya banyak messages
    public function messages()
    {
        return $this->hasMany(Message::class)->latest();
    }

    // Relasi: Conversation punya banyak participants (users)
    public function participants()
    {
        return $this->belongsToMany(User::class)
                    ->withPivot('last_read_at')
                    ->withTimestamps();
    }

    // Relasi: siapa yang membuat conversation
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Ambil pesan terakhir
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // Helper: untuk private chat, ambil nama lawan bicara
    public function getNameForUser(User $user): string
    {
        if ($this->type === 'group') {
            return $this->name ?? 'Group Chat';
        }

        $other = $this->participants->where('id', '!=', $user->id)->first();
        return $other ? $other->name : 'Unknown';
    }

    // Hitung pesan yang belum dibaca oleh user
    public function unreadCount(User $user): int
    {
        $pivot = $this->participants->where('id', $user->id)->first();
        $lastRead = $pivot?->pivot?->last_read_at;

        return $this->messages()
                    ->where('user_id', '!=', $user->id)
                    ->when($lastRead, fn($q) => $q->where('created_at', '>', $lastRead))
                    ->count();
    }
}
