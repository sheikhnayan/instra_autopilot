<?php

namespace App\Jobs;

use App\Models\InstagramAccount;
use App\Models\InstagramPost;
use App\Services\InstagramApiService;
use App\Services\TokenRefreshService;
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
                        // Initialize services
            $instagramService = new InstagramApiService();
            $tokenRefreshService = new TokenRefreshService($instagramService);

            // Ensure we have a valid token (with automatic refresh if needed)
            if (!$tokenRefreshService->ensureValidToken($this->instagramAccount)) {
                Log::error('Unable to obtain valid token for Instagram account', [
                    'account_id' => $this->instagramAccount->id,
                    'username' => $this->instagramAccount->username
                ]);
                
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Account token is invalid or expired and cannot be refreshed - re-authentication required'
                ]);
                return;
            }

            // Refresh the account model to get any updated tokens
            $this->instagramAccount->refresh();

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

            // Check if this is a story or regular post
            if ($this->instagramPost->is_story) {
                // Handle Instagram Story
                $imageUrl = null;
                
                if (!empty($this->instagramPost->images) && isset($this->instagramPost->images[0])) {
                    $imagePath = $this->instagramPost->images[0];
                    $cleanPath = ltrim($imagePath, '/');
                    if (str_starts_with($cleanPath, 'http')) {
                        $imageUrl = $cleanPath;
                    } else {
                        $imageUrl = config('app.url') . '/' . $cleanPath;
                    }
                } elseif ($this->instagramPost->image_path) {
                    $storageUrl = Storage::url($this->instagramPost->image_path);
                    $imageUrl = config('app.url') . $storageUrl;
                }

                if (!$imageUrl) {
                    Log::error('No image found for story', [
                        'post_id' => $this->instagramPost->id,
                        'images' => $this->instagramPost->images,
                        'image_path' => $this->instagramPost->image_path
                    ]);
                    
                    $this->instagramPost->update([
                        'status' => 'failed',
                        'error_message' => 'No image found for story posting'
                    ]);
                    return;
                }

                Log::info('Posting Instagram Story', [
                    'post_id' => $this->instagramPost->id,
                    'image_url' => $imageUrl,
                    'story_stickers' => $this->instagramPost->story_stickers
                ]);

                // Post story to Instagram using Graph API
                $result = $instagramService->postStory(
                    $pageAccessToken,
                    $instagramBusinessAccountId,
                    $imageUrl,
                    $this->instagramPost->story_stickers ?? []
                );
            } else {
                // Handle regular post - Check if post has multiple images (carousel) or single image
                $hasMultipleImages = !empty($this->instagramPost->images) && count($this->instagramPost->images) > 1;
            
            Log::info('Processing Instagram post', [
                'post_id' => $this->instagramPost->id,
                'has_multiple_images' => $hasMultipleImages,
                'image_count' => $hasMultipleImages ? count($this->instagramPost->images) : 1,
                'images_data' => $this->instagramPost->images,
                'image_path' => $this->instagramPost->image_path
            ]);

            if ($hasMultipleImages) {
                // Handle carousel post (multiple images)
                $imageUrls = [];
                foreach ($this->instagramPost->images as $imagePath) {
                    // Clean the path and create proper URL
                    $cleanPath = ltrim($imagePath, '/');
                    // Check if it's already a full URL or needs to be constructed
                    if (str_starts_with($cleanPath, 'http')) {
                        $imageUrls[] = $cleanPath;
                    } else {
                        $imageUrls[] = config('app.url') . '/' . $cleanPath;
                    }
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
                    $imagePath = $this->instagramPost->images[0];
                    $cleanPath = ltrim($imagePath, '/');
                    if (str_starts_with($cleanPath, 'http')) {
                        $imageUrl = $cleanPath;
                    } else {
                        $imageUrl = config('app.url') . '/' . $cleanPath;
                    }
                } elseif ($this->instagramPost->image_path) {
                    $storageUrl = Storage::url($this->instagramPost->image_path);
                    $imageUrl = config('app.url') . $storageUrl;
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
            } // End of else block for regular posts

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

            // Check if this might be a token expiration issue
            $instagramService = new InstagramApiService();
            if ($instagramService->isTokenExpiredError($e->getMessage())) {
                Log::info('Detected token expiration in exception, attempting automatic refresh', [
                    'post_id' => $this->instagramPost->id,
                    'account_id' => $this->instagramAccount->id
                ]);
                
                $tokenRefreshService = new TokenRefreshService($instagramService);
                if ($tokenRefreshService->refreshAccountToken($this->instagramAccount)) {
                    Log::info('Token refreshed successfully after exception, retrying post', [
                        'post_id' => $this->instagramPost->id,
                        'account_id' => $this->instagramAccount->id
                    ]);
                    
                    // Retry the posting with fresh tokens
                    try {
                        $this->handle(); // Recursive call with fresh tokens
                        return; // Success, exit gracefully
                    } catch (\Exception $retryException) {
                        Log::error('Retry after token refresh also failed', [
                            'post_id' => $this->instagramPost->id,
                            'retry_error' => $retryException->getMessage()
                        ]);
                    }
                } else {
                    Log::error('Token refresh failed after exception', [
                        'post_id' => $this->instagramPost->id,
                        'account_id' => $this->instagramAccount->id
                    ]);
                }
            }

            $this->instagramPost->update([
                'status' => 'failed',
                'error_message' => 'Exception: ' . $e->getMessage()
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

    /**
     * Refresh token if needed using the TokenRefreshService
     *
     * @param InstagramApiService $instagramService
     * @return bool
     */
    private function refreshTokenIfNeeded($instagramService)
    {
        $tokenRefreshService = new TokenRefreshService($instagramService);
        return $tokenRefreshService->refreshAccountToken($this->instagramAccount);
    }
}
