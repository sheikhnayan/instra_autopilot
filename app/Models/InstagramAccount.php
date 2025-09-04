<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramAccount extends Model
{
    protected $fillable = [
        'username',
        'display_name',
        'avatar_color',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'instagram_user_id',
        'account_type',
        'media_count',
        'last_sync_at',
        'is_active'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    protected $hidden = [
        'access_token',
        'refresh_token'
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function getAvatarLetterAttribute()
    {
        return strtoupper(substr($this->username, 0, 1));
    }

    public function isTokenValid()
    {
        return $this->token_expires_at && $this->token_expires_at->isFuture();
    }
}
