<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'content_container_id',
        'instagram_account_id',
        'name',
        'start_date',
        'start_time',
        'interval_minutes',
        'status',
        'last_posted_at',
        'current_post_index',
        'repeat_cycle'
    ];

    protected $casts = [
        'start_date' => 'date',
        'last_posted_at' => 'datetime',
        'repeat_cycle' => 'boolean'
    ];

    public function contentContainer()
    {
        return $this->belongsTo(ContentContainer::class);
    }

    public function instagramAccount()
    {
        return $this->belongsTo(InstagramAccount::class);
    }
}
