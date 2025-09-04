<?php

namespace App\Jobs;

use App\Models\InstagramAccount;
use App\Models\InstagramPost;
use App\Services\InstagramApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PostToInstagramJob implements ShouldQueue
{
    use Queueable;

    protected $instagramAccount;
    protected $instagramPost;

    /**
     * Create a new job instance.
     */
    public function __construct(InstagramAccount $instagramAccount, InstagramPost $instagramPost)
    {
        $this->instagramAccount = $instagramAccount;
        $this->instagramPost = $instagramPost;
    }

    /**
     * Execute the job.
     */
    public function handle(InstagramApiService $instagramService): void
    {
        try {
            // Check if account has valid token
            if (!$this->instagramAccount->access_token || !$this->instagramAccount->isTokenValid()) {
                Log::error('Instagram account token is invalid or expired', [
                    'account_id' => $this->instagramAccount->id,
                    'username' => $this->instagramAccount->username
                ]);
                
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Account token is invalid or expired'
                ]);
                return;
            }

            // Validate that the token still works
            if (!$instagramService->validateToken($this->instagramAccount->access_token)) {
                Log::error('Instagram token validation failed', [
                    'account_id' => $this->instagramAccount->id,
                    'username' => $this->instagramAccount->username
                ]);
                
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Token validation failed'
                ]);
                return;
            }

            // Get the full URL for the image
            $imageUrl = Storage::url($this->instagramPost->image_path);
            $fullImageUrl = config('app.url') . $imageUrl;

            // Use Facebook Page access token and Instagram Business Account ID
            $pageAccessToken = $this->instagramAccount->facebook_page_access_token;
            $instagramBusinessAccountId = $this->instagramAccount->instagram_business_account_id;

            if (!$pageAccessToken || !$instagramBusinessAccountId) {
                Log::error('Missing Facebook Page token or Instagram Business Account ID', [
                    'account_id' => $this->instagramAccount->id,
                    'username' => $this->instagramAccount->username
                ]);
                
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Missing Facebook Page token or Instagram Business Account ID'
                ]);
                return;
            }

            // Post to Instagram using Graph API
            $result = $instagramService->postPhoto(
                $pageAccessToken,
                $instagramBusinessAccountId,
                $fullImageUrl,
                $this->instagramPost->caption
            );

            if ($result && isset($result['id'])) {
                // Success
                $this->instagramPost->update([
                    'status' => 'posted',
                    'instagram_media_id' => $result['id'],
                    'posted_at' => now(),
                    'error_message' => null
                ]);

                Log::info('Successfully posted to Instagram', [
                    'post_id' => $this->instagramPost->id,
                    'instagram_media_id' => $result['id'],
                    'account_username' => $this->instagramAccount->username
                ]);

            } else {
                // Failed
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to post to Instagram - no media ID returned'
                ]);

                Log::error('Failed to post to Instagram', [
                    'post_id' => $this->instagramPost->id,
                    'account_username' => $this->instagramAccount->username,
                    'response' => $result
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Exception occurred while posting to Instagram', [
                'post_id' => $this->instagramPost->id,
                'account_username' => $this->instagramAccount->username,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->instagramPost->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            // Re-throw the exception to trigger job retry if needed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('PostToInstagramJob failed permanently', [
            'post_id' => $this->instagramPost->id,
            'account_username' => $this->instagramAccount->username,
            'exception' => $exception->getMessage()
        ]);

        $this->instagramPost->update([
            'status' => 'failed',
            'error_message' => 'Job failed permanently: ' . $exception->getMessage()
        ]);
    }
}
