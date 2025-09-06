<?php

namespace App\Jobs;

use App\Models\InstagramAccount;
use App\Services\InstagramApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportInstagramAccountsBatch implements ShouldQueue
{
    use Queueable;

    protected $userAccessToken;
    protected $nextUrl;
    protected $batchNumber;
    protected $maxBatches;

    /**
     * Create a new job instance.
     */
    public function __construct($userAccessToken, $nextUrl = null, $batchNumber = 1, $maxBatches = 20)
    {
        $this->userAccessToken = $userAccessToken;
        $this->nextUrl = $nextUrl;
        $this->batchNumber = $batchNumber;
        $this->maxBatches = $maxBatches;
    }

    /**
     * Execute the job.
     */
    public function handle(InstagramApiService $instagramService): void
    {
        try {
            Log::info('Starting Instagram account import batch', [
                'batch_number' => $this->batchNumber,
                'max_batches' => $this->maxBatches,
                'has_next_url' => !empty($this->nextUrl)
            ]);

            // Import this batch of accounts
            $result = $instagramService->importAccountsBatch(
                $this->userAccessToken, 
                $this->nextUrl,
                25 // batch size for 512MB RAM
            );

            $importedCount = 0;
            $errors = [];

            // Process each account in this batch
            foreach ($result['accounts'] as $accountData) {
                try {
                    $igAccount = $accountData['instagram_account'];
                    
                    $instagramAccount = InstagramAccount::updateOrCreate(
                        ['instagram_business_account_id' => $igAccount['id']],
                        [
                            'username' => $igAccount['username'],
                            'display_name' => $igAccount['name'] ?? $igAccount['username'],
                            'avatar_color' => $this->generateRandomColor(),
                            'access_token' => $this->userAccessToken,
                            'token_expires_at' => now()->addSeconds(5184000), // 60 days
                            'instagram_user_id' => $igAccount['id'],
                            'instagram_business_account_id' => $igAccount['id'],
                            'account_type' => 'BUSINESS',
                            'media_count' => $igAccount['media_count'] ?? 0,
                            'facebook_page_id' => $accountData['facebook_page_id'],
                            'facebook_page_access_token' => $accountData['facebook_page_access_token'],
                            'last_sync_at' => now(),
                            'is_active' => true
                        ]
                    );

                    $importedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Failed to import @{$igAccount['username']}: " . $e->getMessage();
                    Log::error('Failed to import Instagram account in batch', [
                        'username' => $igAccount['username'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'batch_number' => $this->batchNumber
                    ]);
                }
            }

            Log::info('Completed Instagram account import batch', [
                'batch_number' => $this->batchNumber,
                'imported_count' => $importedCount,
                'error_count' => count($errors),
                'has_more_pages' => !empty($result['next_url'])
            ]);

            // If there are more pages and we haven't reached max batches, queue next batch
            if (!empty($result['next_url']) && $this->batchNumber < $this->maxBatches) {
                Log::info('Queueing next batch for import', [
                    'next_batch_number' => $this->batchNumber + 1,
                    'next_url_exists' => !empty($result['next_url'])
                ]);

                // Queue the next batch with a 30-second delay to prevent server overload
                ImportInstagramAccountsBatch::dispatch(
                    $this->userAccessToken,
                    $result['next_url'],
                    $this->batchNumber + 1,
                    $this->maxBatches
                )->delay(now()->addSeconds(30));
            } else {
                Log::info('Instagram account import completed', [
                    'reason' => empty($result['next_url']) ? 'no_more_pages' : 'max_batches_reached',
                    'total_batches_processed' => $this->batchNumber,
                    'max_batches_allowed' => $this->maxBatches
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Instagram account import batch failed', [
                'batch_number' => $this->batchNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't queue next batch if this one failed
            throw $e;
        }
    }

    /**
     * Generate a random color for avatar
     */
    private function generateRandomColor()
    {
        $colors = ['bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500', 'bg-pink-500', 'bg-indigo-500'];
        return $colors[array_rand($colors)];
    }
}
