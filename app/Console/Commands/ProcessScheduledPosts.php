<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPostsJob;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
        
        // Add debug logging
        Log::info('ProcessScheduledPosts command started', [
            'timestamp' => now()->toDateTimeString(),
            'memory_usage' => memory_get_usage(true)
        ]);

        // Get all active schedules that are ready to execute
        $schedules = Schedule::where('status', 'active')
            ->with(['instagramAccount', 'contentContainer.posts'])
            ->get();

        Log::info('Found schedules', [
            'count' => $schedules->count(),
            'schedule_ids' => $schedules->pluck('id')->toArray()
        ]);

        if ($schedules->isEmpty()) {
            $this->info('No schedules ready for execution.');
            Log::info('No active schedules found');
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
                    continue;
                }
                
                $this->info("Processing post ID: {$nextPost->id} for schedule ID: {$schedule->id}");
                
                // Mark post as scheduled (this prevents the scheduler from picking it up again)
                $nextPost->update(['status' => 'scheduled']);
                
                // Dispatch the Instagram posting job
                \App\Jobs\PostToInstagramJob::dispatch(
                    $schedule->instagramAccount,
                    $nextPost
                );
                
                // Update schedule's last posted time and set current index to this post's order
                $schedule->update([
                    'last_posted_at' => now(),
                    'current_post_index' => $nextPost->order
                ]);
                
                $this->info("✓ Schedule ID: {$schedule->id} - Post ID: {$nextPost->id} dispatched successfully");
                
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
            $startDateTime = $schedule->start_date->format('Y-m-d') . ' ' . $schedule->start_time;
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
        $allPosts = $schedule->contentContainer->posts()
            ->orderBy('order')
            ->get();
            
        // Get posts by status
        $draftPosts = $allPosts->where('status', 'draft');
        $postedPosts = $allPosts->whereIn('status', ['posted', 'failed']);
        $scheduledPosts = $allPosts->where('status', 'scheduled');
        
        $this->info("Status check - Total: {$allPosts->count()}, Completed: {$postedPosts->count()}, Scheduled: {$scheduledPosts->count()}, Draft: {$draftPosts->count()}");
        
        // Use the schedule's current_post_index to get the EXACT next post by order
        $currentIndex = $schedule->current_post_index ?? 0;
        
        // Get the post at the specific index position (by order)
        $nextPost = $allPosts->where('order', '>=', $currentIndex + 1)->where('status', 'draft')->first();
        
        if (!$nextPost) {
            // If no post found at current index, try to find the next available draft post
            $nextPost = $draftPosts->first();
        }
        
        if ($nextPost) {
            $this->info("Found next post to schedule: ID {$nextPost->id} (order: {$nextPost->order}, current_index: {$currentIndex})");
            return $nextPost;
        }
        
        // If no more draft posts, check if we should mark schedule as completed
        $totalPosts = $allPosts->count();
        $completedPosts = $allPosts->whereIn('status', ['posted', 'failed'])->count();
        $scheduledPosts = $allPosts->where('status', 'scheduled')->count();
        $draftPosts = $availablePosts->count();
        
        $this->info("Status check - Total: {$totalPosts}, Completed: {$completedPosts}, Scheduled: {$scheduledPosts}, Draft: {$draftPosts}");
        
        // If there are still draft posts available, something is wrong with the index logic
        if ($draftPosts->count() > 0) {
            $this->info("Found {$draftPosts->count()} draft posts but couldn't get next post. Resetting index to find correct post.");
            
            // Find the next post in sequence that's still draft
            $nextInSequence = $allPosts->where('status', 'draft')->sortBy('order')->first();
            if ($nextInSequence) {
                // Update index to be one less than the found post's order (since it will be incremented)
                $schedule->update(['current_post_index' => $nextInSequence->order - 1]);
                return $nextInSequence;
            }
        }
        
        // If all posts have been processed (posted/failed) and no repeat cycle, mark as completed
        if ($draftPosts->count() == 0 && $postedPosts->count() >= $allPosts->count() && !$schedule->repeat_cycle) {
            $this->info("All posts processed for schedule ID: {$schedule->id}. Marking as completed.");
            $schedule->update(['status' => 'completed']);
            return null;
        }
        
        // If repeat cycle is enabled and no draft posts remain, reset all posts to draft
        if ($schedule->repeat_cycle && $draftPosts->count() == 0) {
            $this->info("Repeat cycle enabled for schedule ID: {$schedule->id}. Resetting all posts to draft and starting over.");
            
            // Reset all posts back to draft status for repeat cycle
            $schedule->contentContainer->posts()->update(['status' => 'draft']);
            $schedule->update(['current_post_index' => 0]);
            
            // Get the first post after reset (by order)
            return $allPosts->sortBy('order')->first();
        }
        
        // If there are still posts being scheduled/processed, wait
        if ($scheduledPosts > 0) {
            $this->info("Schedule ID: {$schedule->id} has {$scheduledPosts} posts currently being processed. Waiting...");
        }
        
        $this->info("No more posts to publish for schedule ID: {$schedule->id}");
        return null;
    }
}
