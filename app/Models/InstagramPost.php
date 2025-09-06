<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramPost extends Model
{
    protected $fillable = [
        'content_container_id',
        'caption',
        'images',
        'image_path',
        'hashtags',
        'post_type',
        'is_story',
        'story_stickers',
        'story_duration',
        'order',
        'status',
        'posted_at',
        'instagram_post_id',
        'error_message'
    ];

    protected $casts = [
        'images' => 'array',
        'hashtags' => 'array',
        'story_stickers' => 'array',
        'is_story' => 'boolean',
        'posted_at' => 'datetime'
    ];

    public function contentContainer()
    {
        return $this->belongsTo(ContentContainer::class);
    }
}
