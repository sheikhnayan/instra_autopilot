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
    
    // Job retry configuration
    public $tries = 3; // Try up to 3 times
    public $backoff = [60, 300]; // Wait 1 minute, then 5 minutes between retries

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
            Log::info('Starting Instagram post job', [
                'post_id' => $this->instagramPost->id,
                'account_id' => $this->instagramAccount->id,
                'attempt' => $this->attempts()
            ]);
            
            // Use Facebook Page access token and Instagram Business Account ID
            $pageAccessToken = $this->instagramAccount->facebook_page_access_token;
            $instagramBusinessAccountId = $this->instagramAccount->instagram_business_account_id;

            if (!$pageAccessToken || !$instagramBusinessAccountId) {
                $errorMessage = 'Missing Facebook Page token or Instagram Business Account ID - please reconnect your account';
                
                Log::error('Missing credentials', [
                    'account_id' => $this->instagramAccount->id,
                    'username' => $this->instagramAccount->username,
                    'has_page_token' => !empty($pageAccessToken),
                    'has_business_id' => !empty($instagramBusinessAccountId)
                ]);
                
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage
                ]);
                
                // Don't retry for missing credentials
                $this->fail(new \Exception($errorMessage));
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
                // Success - Post was successfully created on Instagram
                $this->instagramPost->update([
                    'status' => 'posted',
                    'instagram_media_id' => $result['id'],
                    'posted_at' => now(),
                    'error_message' => null
                ]);

                Log::info('Successfully posted to Instagram', [
                    'post_id' => $this->instagramPost->id,
                    'instagram_media_id' => $result['id'],
                    'account_username' => $this->instagramAccount->username,
                    'attempt' => $this->attempts()
                ]);

            } else {
                // API call didn't return a valid response
                $errorMessage = 'Instagram API did not return a valid media ID';
                
                Log::warning('Instagram API response invalid', [
                    'post_id' => $this->instagramPost->id,
                    'account_username' => $this->instagramAccount->username,
                    'response' => $result,
                    'attempt' => $this->attempts()
                ]);
                
                // Check if we should retry or fail permanently
                if ($this->attempts() >= $this->tries) {
                    // Final attempt failed
                    $this->instagramPost->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage . ' (after ' . $this->tries . ' attempts)'
                    ]);
                    
                    Log::error('Instagram post failed permanently', [
                        'post_id' => $this->instagramPost->id,
                        'account_username' => $this->instagramAccount->username,
                        'final_response' => $result
                    ]);
                } else {
                    // Will retry, so don't update status to failed yet
                    Log::info('Will retry Instagram post', [
                        'post_id' => $this->instagramPost->id,
                        'attempt' => $this->attempts(),
                        'max_tries' => $this->tries
                    ]);
                    
                    throw new \Exception($errorMessage);
                }
            }

        } catch (\Exception $e) {
            Log::error('Exception occurred while posting to Instagram', [
                'post_id' => $this->instagramPost->id,
                'account_username' => $this->instagramAccount->username,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'trace' => $e->getTraceAsString()
            ]);

            // Check if this is a permanent error that shouldn't be retried
            $errorMessage = $e->getMessage();
            $isPermanentError = stripos($errorMessage, 'token') !== false || 
                               stripos($errorMessage, 'authorization') !== false ||
                               stripos($errorMessage, 'oauth') !== false ||
                               stripos($errorMessage, 'expired') !== false ||
                               stripos($errorMessage, 'invalid credentials') !== false;

            if ($isPermanentError) {
                // Don't retry for token/auth issues
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Authentication error: ' . $errorMessage . ' - please reconnect your account'
                ]);
                
                $this->fail($e);
                return;
            }
            
            // Check if we've reached max attempts
            if ($this->attempts() >= $this->tries) {
                // Final attempt - mark as failed
                $this->instagramPost->update([
                    'status' => 'failed',
                    'error_message' => 'Failed after ' . $this->tries . ' attempts: ' . $errorMessage
                ]);
            }

            // Re-throw the exception to trigger job retry if not at max attempts
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

        // Check if this looks like a token issue
        $errorMessage = $exception->getMessage();
        $isTokenIssue = stripos($errorMessage, 'token') !== false || 
                       stripos($errorMessage, 'authorization') !== false ||
                       stripos($errorMessage, 'oauth') !== false ||
                       stripos($errorMessage, 'expired') !== false;

        if ($isTokenIssue) {
            $this->instagramPost->update([
                'status' => 'failed',
                'error_message' => 'Token expired or invalid - please reconnect your Instagram account'
            ]);
        } else {
            $this->instagramPost->update([
                'status' => 'failed',
                'error_message' => 'Job failed permanently: ' . $exception->getMessage()
            ]);
        }
    }
}
