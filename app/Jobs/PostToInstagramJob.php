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

            // Check if post has multiple images (carousel) or single image
            $hasMultipleImages = !empty($this->instagramPost->images) && count($this->instagramPost->images) > 1;
            
            Log::info('Processing Instagram post', [
                'post_id' => $this->instagramPost->id,
                'has_multiple_images' => $hasMultipleImages,
                'image_count' => $hasMultipleImages ? count($this->instagramPost->images) : 1,
                'images_data' => $this->instagramPost->images
            ]);

            if ($hasMultipleImages) {
                // Handle carousel post (multiple images)
                $imageUrls = [];
                foreach ($this->instagramPost->images as $imagePath) {
                    // Remove leading slash if present and ensure proper URL format
                    $cleanPath = ltrim($imagePath, '/');
                    $imageUrls[] = config('app.url') . '/' . $cleanPath;
                }

                Log::info('Posting carousel with URLs', ['urls' => $imageUrls]);

                // Post carousel to Instagram using Graph API
                $result = $instagramService->postCarousel(
                    $pageAccessToken,
                    $instagramBusinessAccountId,
                    $imageUrls,
                    $this->instagramPost->caption
                );
            } else {
                // Handle single image post
                $imageUrl = null;
                
                // Try to get image from images array first, then fallback to image_path
                if (!empty($this->instagramPost->images) && isset($this->instagramPost->images[0])) {
                    $cleanPath = ltrim($this->instagramPost->images[0], '/');
                    $imageUrl = config('app.url') . '/' . $cleanPath;
                } elseif ($this->instagramPost->image_path) {
                    $imageUrl = Storage::url($this->instagramPost->image_path);
                    $imageUrl = config('app.url') . $imageUrl;
                }

                if (!$imageUrl) {
                    Log::error('No image found for post', [
                        'post_id' => $this->instagramPost->id,
                        'images' => $this->instagramPost->images,
                        'image_path' => $this->instagramPost->image_path
                    ]);
                    
                    $this->instagramPost->update([
                        'status' => 'failed',
                        'error_message' => 'No image found for posting'
                    ]);
                    return;
                }

                Log::info('Posting single image with URL', ['url' => $imageUrl]);

                // Post single image to Instagram using Graph API
                $result = $instagramService->postPhoto(
                    $pageAccessToken,
                    $instagramBusinessAccountId,
                    $imageUrl,
                    $this->instagramPost->caption
                );
            }

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
