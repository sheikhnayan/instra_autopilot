<?php

namespace App\Console\Commands;

use App\Models\InstagramPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStuckPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:cleanup-stuck-posts {--minutes=30 : Reset posts that have been scheduled for this many minutes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset posts that have been stuck in scheduled status for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $minutes = $this->option('minutes');
        
        $this->info("Looking for posts stuck in 'scheduled' status for more than {$minutes} minutes...");
        
        // Find posts that have been scheduled for too long
        $stuckPosts = InstagramPost::where('status', 'scheduled')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->get();
            
        if ($stuckPosts->isEmpty()) {
            $this->info('No stuck posts found.');
            return;
        }
        
        $this->info("Found {$stuckPosts->count()} posts that appear to be stuck.");
        
        foreach ($stuckPosts as $post) {
            $this->info("Resetting post {$post->id} back to draft status...");
            
            $post->update([
                'status' => 'draft',
                'error_message' => 'Reset from stuck scheduled status on ' . now()->format('Y-m-d H:i:s')
            ]);
            
            Log::info('Reset stuck Instagram post', [
                'post_id' => $post->id,
                'stuck_since' => $post->updated_at
            ]);
        }
        
        $this->info("✓ {$stuckPosts->count()} stuck posts reset to draft status.");
    }
}
