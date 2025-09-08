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
    
    /**
     * Get the next post time in New York timezone
     */
    public function getNextPostTime()
    {
        if (!$this->last_posted_at) {
            return null;
        }
        
        return $this->last_posted_at
            ->setTimezone('America/New_York')
            ->copy()
            ->addMinutes($this->interval_minutes);
    }
    
    /**
     * Get the last posted time in New York timezone
     */
    public function getLastPostedTimeNY()
    {
        if (!$this->last_posted_at) {
            return null;
        }
        
        return $this->last_posted_at->setTimezone('America/New_York');
    }
}
