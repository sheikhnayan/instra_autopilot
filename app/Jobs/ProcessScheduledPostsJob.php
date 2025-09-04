<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\InstagramPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessScheduledPostsJob implements ShouldQueue
{
    use Queueable;

    protected $schedule;

    /**
     * Create a new job instance.
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if schedule is active and should run
        if (!$this->schedule->is_active || !$this->shouldRun()) {
            return;
        }

        try {
            // Get the next post from the container that hasn't been posted yet
            $nextPost = $this->schedule->contentContainer->posts()
                ->where('status', 'draft')
                ->orderBy('order')
                ->first();

            if (!$nextPost) {
                // No more posts to publish, mark schedule as completed
                $this->schedule->update([
                    'is_active' => false,
                    'last_executed_at' => now()
                ]);

                Log::info('Schedule completed - no more posts to publish', [
                    'schedule_id' => $this->schedule->id,
                    'container_id' => $this->schedule->content_container_id
                ]);
                return;
            }

            // Mark post as scheduled
            $nextPost->update(['status' => 'scheduled']);

            // Dispatch the Instagram posting job
            PostToInstagramJob::dispatch(
                $this->schedule->instagramAccount,
                $nextPost
            );

            // Update schedule's last execution time and next execution time
            $this->schedule->update([
                'last_executed_at' => now(),
                'next_execution_at' => $this->calculateNextExecution()
            ]);

            Log::info('Scheduled post dispatched', [
                'schedule_id' => $this->schedule->id,
                'post_id' => $nextPost->id,
                'next_execution' => $this->schedule->next_execution_at
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing scheduled posts', [
                'schedule_id' => $this->schedule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Determine if the schedule should run now
     */
    private function shouldRun(): bool
    {
        $now = Carbon::now();
        
        // Check if start date has passed
        if ($this->schedule->start_date && $now->lt($this->schedule->start_date)) {
            return false;
        }

        // Check if end date has passed
        if ($this->schedule->end_date && $now->gt($this->schedule->end_date)) {
            $this->schedule->update(['is_active' => false]);
            return false;
        }

        // Check if next execution time has arrived
        if ($this->schedule->next_execution_at && $now->lt($this->schedule->next_execution_at)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the next execution time based on interval
     */
    private function calculateNextExecution(): Carbon
    {
        $now = Carbon::now();
        
        // Parse interval (e.g., "60 minutes", "2 hours", "1 day")
        $intervalParts = explode(' ', $this->schedule->interval);
        $value = (int) $intervalParts[0];
        $unit = $intervalParts[1] ?? 'minutes';

        switch (strtolower($unit)) {
            case 'minute':
            case 'minutes':
                return $now->addMinutes($value);
            case 'hour':
            case 'hours':
                return $now->addHours($value);
            case 'day':
            case 'days':
                return $now->addDays($value);
            default:
                return $now->addMinutes($value); // Default to minutes
        }
    }
}
