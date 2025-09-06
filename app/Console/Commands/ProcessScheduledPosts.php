<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPostsJob;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:process-scheduled-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled Instagram posts based on their intervals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process scheduled posts...');

        // Get all active schedules that are ready to execute
        $schedules = Schedule::where('status', 'active')
            ->with(['instagramAccount', 'contentContainer.posts'])
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules ready for execution.');
            return;
        }

        $this->info("Found {$schedules->count()} schedule(s) ready for execution.");

        foreach ($schedules as $schedule) {
            try {
                $this->info("Checking schedule ID: {$schedule->id} for container: {$schedule->contentContainer->name}");
                
                // Check if it's time to post based on interval
                if (!$this->shouldPostNow($schedule)) {
                    $this->info("Not time to post yet for schedule ID: {$schedule->id}");
                    continue;
                }
                
                // Get the next post from the container that hasn't been posted yet
                $nextPost = $this->getNextPost($schedule);
                
                if (!$nextPost) {
                    $this->info("No more posts to publish for schedule ID: {$schedule->id}");
                    
                    if ($schedule->repeat_cycle) {
                        // Reset to first post if repeat cycle is enabled
                        $schedule->update(['current_post_index' => 0]);
                        $nextPost = $this->getNextPost($schedule);
                    }
                    
                    if (!$nextPost) {
                        // Mark schedule as completed if no repeat cycle
                        $schedule->update(['status' => 'completed']);
                        continue;
                    }
                }
                
                // Mark post as scheduled
                $nextPost->update(['status' => 'scheduled']);
                
                // Dispatch the Instagram posting job
                \App\Jobs\PostToInstagramJob::dispatch(
                    $schedule->instagramAccount,
                    $nextPost
                );
                
                // Update schedule's last posted time and increment post index
                $schedule->update([
                    'last_posted_at' => now(),
                    'current_post_index' => ($schedule->current_post_index ?? 0) + 1
                ]);
                
                $this->info("✓ Schedule ID: {$schedule->id} - Post dispatched successfully");
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to process schedule ID: {$schedule->id} - Error: {$e->getMessage()}");
            }
        }

        $this->info('Finished processing scheduled posts.');
    }
    
    /**
     * Check if it's time to post based on schedule
     */
    private function shouldPostNow($schedule)
    {
        // Set timezone to New York for all time calculations
        $ny_tz = new \DateTimeZone('America/New_York');
        $now = Carbon::now($ny_tz);
        
        // If never posted before, check if start time has passed
        if (!$schedule->last_posted_at) {
            $startDateTime = $schedule->start_date->format('Y-m-d') . ' ' . $schedule->start_time->format('H:i:s');
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $startDateTime, $ny_tz);
            
            $this->info("Start time: {$startDateTime->format('Y-m-d H:i:s T')} | Current time: {$now->format('Y-m-d H:i:s T')}");
            
            return $now->greaterThanOrEqualTo($startDateTime);
        }
        
        // Check if enough time has passed since last post
        $lastPosted = Carbon::parse($schedule->last_posted_at)->setTimezone($ny_tz);
        $nextPostTime = $lastPosted->addMinutes($schedule->interval_minutes);
        
        $this->info("Last posted: {$lastPosted->format('Y-m-d H:i:s T')} | Next post time: {$nextPostTime->format('Y-m-d H:i:s T')} | Current time: {$now->format('Y-m-d H:i:s T')}");
        
        return $now->greaterThanOrEqualTo($nextPostTime);
    }
    
    /**
     * Get the next post to publish from the container
     */
    private function getNextPost($schedule)
    {
        $posts = $schedule->contentContainer->posts()
            ->where('status', 'draft')
            ->orderBy('order')
            ->get();
            
        $currentIndex = $schedule->current_post_index ?? 0;
        
        return $posts->skip($currentIndex)->first();
    }
}
