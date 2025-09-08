<?php

namespace App\Console\Commands;

use App\Models\InstagramPost;
use App\Jobs\PostToInstagramJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:retry-failed-posts {--minutes=60 : Only retry posts that failed within this many minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry Instagram posts that failed due to temporary issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = $this->option('minutes');
        
        $this->info("Looking for failed posts from the last {$minutes} minutes...");
        
        // Find posts that failed recently and might be retryable
        $failedPosts = InstagramPost::where('status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes($minutes))
            ->whereNotNull('error_message')
            ->with(['contentContainer.schedules.instagramAccount'])
            ->get();
            
        $retryablePosts = $failedPosts->filter(function ($post) {
            $errorMessage = strtolower($post->error_message);
            
            // Don't retry posts with permanent errors
            $permanentErrors = ['token', 'authorization', 'oauth', 'expired', 'missing facebook page token', 'missing credentials'];
            foreach ($permanentErrors as $permanentError) {
                if (str_contains($errorMessage, $permanentError)) {
                    return false;
                }
            }
            
            return true;
        });
        
        if ($retryablePosts->isEmpty()) {
            $this->info('No retryable failed posts found.');
            return;
        }
        
        $this->info("Found {$retryablePosts->count()} posts that can be retried.");
        
        foreach ($retryablePosts as $post) {
            // Get the associated Instagram account from the schedule
            $schedule = $post->contentContainer->schedules->first();
            if (!$schedule || !$schedule->instagramAccount) {
                $this->warn("Skipping post {$post->id} - no associated Instagram account found");
                continue;
            }
            
            $this->info("Retrying post {$post->id}...");
            
            // Reset status to scheduled for retry
            $post->update([
                'status' => 'scheduled',
                'error_message' => 'Retrying failed post on ' . now()->format('Y-m-d H:i:s')
            ]);
            
            // Dispatch the job again
            PostToInstagramJob::dispatch($schedule->instagramAccount, $post);
            
            Log::info('Retrying failed Instagram post', [
                'post_id' => $post->id,
                'original_error' => $post->error_message
            ]);
        }
        
        $this->info("✓ {$retryablePosts->count()} posts queued for retry.");
    }
}
