<?php

namespace App\Console\Commands;

use App\Jobs\PostToInstagramJob;
use App\Models\InstagramAccount;
use App\Models\InstagramPost;
use Illuminate\Console\Command;

class TestInstagramPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:test-post {account_id} {post_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test posting a specific post to Instagram';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountId = $this->argument('account_id');
        $postId = $this->argument('post_id');

        $account = InstagramAccount::find($accountId);
        $post = InstagramPost::find($postId);

        if (!$account) {
            $this->error("Instagram account with ID {$accountId} not found.");
            return 1;
        }

        if (!$post) {
            $this->error("Instagram post with ID {$postId} not found.");
            return 1;
        }

        if (!$account->is_active || !$account->access_token) {
            $this->error("Instagram account is not connected or active.");
            return 1;
        }

        $this->info("Testing Instagram post...");
        $this->info("Account: {$account->username}");
        $this->info("Post Caption: " . substr($post->caption, 0, 50) . "...");
        
        // Dispatch the job
        PostToInstagramJob::dispatch($account, $post);
        
        $this->info("Post job dispatched successfully! Check the queue worker for results.");
        
        return 0;
    }
}
