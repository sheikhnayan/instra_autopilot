<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentContainer extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'post_count',
        'is_active'
    ];

    protected $casts = [
        'post_count' => 'array',
        'is_active' => 'boolean'
    ];

    public function posts()
    {
        return $this->hasMany(InstagramPost::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
