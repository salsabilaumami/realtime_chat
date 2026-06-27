<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_online',
        'last_seen',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'last_seen'         => 'datetime',
            'is_online'         => 'boolean',
        ];
    }

    // Relasi: User punya banyak conversations (lewat pivot)
    public function conversations()
    {
        return $this->belongsToMany(Conversation::class)
                    ->withPivot('last_read_at')
                    ->withTimestamps()
                    ->latest('updated_at');
    }

    // Relasi: User punya banyak messages
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    // Helper: ambil inisial nama untuk avatar default
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }

    // Helper: format waktu terakhir online
    public function getLastSeenFormattedAttribute(): string
    {
        if ($this->is_online) {
            return 'Online';
        }
        if (!$this->last_seen) {
            return 'Belum pernah online';
        }
        return $this->last_seen->diffForHumans();
    }
}
