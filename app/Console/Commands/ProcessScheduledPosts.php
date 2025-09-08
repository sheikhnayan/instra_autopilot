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

        // Run cleanup commands first
        $this->runMaintenanceCommands();

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
                
                // Update schedule's last posted time (in New York timezone)
                $nyTimezone = new \DateTimeZone('America/New_York');
                $schedule->update([
                    'last_posted_at' => Carbon::now($nyTimezone)
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
        $nextPostTime = $lastPosted->copy()->addMinutes($schedule->interval_minutes);
        
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
            
        $this->info("Found {$posts->count()} draft posts for schedule ID: {$schedule->id}");
        
        if ($posts->isEmpty()) {
            // Check if there are any posts currently being processed
            $scheduledPosts = $schedule->contentContainer->posts()
                ->where('status', 'scheduled')
                ->count();
            
            if ($scheduledPosts > 0) {
                $this->info("Schedule ID: {$schedule->id} has {$scheduledPosts} posts currently being processed. Waiting...");
                return null;
            }
            
            // Check if repeat cycle is enabled
            if ($schedule->repeat_cycle) {
                $this->info("Repeat cycle enabled for schedule ID: {$schedule->id}. Resetting all posts to draft.");
                
                // Reset all posts back to draft status for repeat cycle
                $schedule->contentContainer->posts()->update(['status' => 'draft']);
                
                // Get the first post after reset
                return $schedule->contentContainer->posts()
                    ->where('status', 'draft')
                    ->orderBy('order')
                    ->first();
            } else {
                // No more posts and no repeat cycle - mark schedule as completed
                $this->info("No more draft posts for schedule ID: {$schedule->id}. Marking as completed.");
                $schedule->update(['status' => 'completed']);
                return null;
            }
        }
        
        return $posts->first();
    }
    
    /**
     * Run maintenance commands for cleanup and retries
     */
    private function runMaintenanceCommands()
    {
        // Only run maintenance every 10 minutes to avoid excessive overhead
        $lastMaintenanceKey = 'last_maintenance_run';
        $lastRun = cache($lastMaintenanceKey);
        
        if ($lastRun && now()->diffInMinutes($lastRun) < 10) {
            return; // Skip if maintenance was run recently
        }
        
        $this->info('Running maintenance commands...');
        
        try {
            // Cleanup stuck posts (posts stuck in 'scheduled' status for more than 30 minutes)
            $this->info('• Cleaning up stuck posts...');
            $this->call('instagram:cleanup-stuck-posts', ['--minutes' => 30]);
            
            // Retry failed posts from the last 2 hours (but not auth errors)
            $this->info('• Retrying failed posts...');
            $this->call('instagram:retry-failed-posts', ['--minutes' => 120]);
            
            // Cache that we ran maintenance
            cache([$lastMaintenanceKey => now()], now()->addMinutes(15));
            
            $this->info('✓ Maintenance commands completed.');
            
        } catch (\Exception $e) {
            $this->error("✗ Maintenance failed: {$e->getMessage()}");
            Log::error('Maintenance commands failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
