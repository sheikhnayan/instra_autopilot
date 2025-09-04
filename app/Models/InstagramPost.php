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
        'order',
        'status',
        'posted_at',
        'instagram_post_id',
        'error_message'
    ];

    protected $casts = [
        'images' => 'array',
        'hashtags' => 'array',
        'posted_at' => 'datetime'
    ];

    public function contentContainer()
    {
        return $this->belongsTo(ContentContainer::class);
    }
}
