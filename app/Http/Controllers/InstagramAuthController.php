<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Services\InstagramApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstagramAuthController extends Controller
{
    protected $instagramService;

    public function __construct(InstagramApiService $instagramService)
    {
        $this->instagramService = $instagramService;
    }

    /**
     * Redirect to Instagram authorization
     */
    public function redirectToInstagram()
    {
        $authUrl = $this->instagramService->getAuthorizationUrl();
        return redirect($authUrl);
    }

    /**
     * Handle Instagram callback and import all connected accounts
     */
    public function handleInstagramCallback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        // Verify CSRF token
        if ($state !== csrf_token()) {
            return redirect()->route('dashboard')->with('error', 'Invalid state parameter');
        }

        if ($error) {
            return redirect()->route('dashboard')->with('error', 'Authorization failed: ' . $error);
        }

        if (!$code) {
            return redirect()->route('dashboard')->with('error', 'No authorization code received');
        }

        try {
            // Step 1: Get short-lived user access token
            $tokenResponse = $this->instagramService->getAccessToken($code);
            
            if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
                return redirect()->route('dashboard')->with('error', 'Failed to get access token');
            }

            // Step 2: Exchange for long-lived user token (60 days)
            $longLivedTokenResponse = $this->instagramService->getLongLivedUserToken($tokenResponse['access_token']);
            
            $userAccessToken = $longLivedTokenResponse['access_token'] ?? $tokenResponse['access_token'];
            $expiresIn = $longLivedTokenResponse['expires_in'] ?? 3600;
            
            // Step 3: Import first batch immediately, queue the rest
            Log::info('Starting Instagram account import process', [
                'token_length' => strlen($userAccessToken),
                'token_starts_with' => substr($userAccessToken, 0, 20) . '...',
                'server_memory_limit' => ini_get('memory_limit'),
                'current_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB'
            ]);
            
            // Import first batch immediately (30 accounts)
            $firstBatchResult = $this->instagramService->importAccountsBatch($userAccessToken, null, 30);
            
            $importedCount = 0;
            $errors = [];

            // Process the first batch accounts
            foreach ($firstBatchResult['accounts'] as $accountData) {
                try {
                    $igAccount = $accountData['instagram_account'];
                    
                    $instagramAccount = InstagramAccount::updateOrCreate(
                        ['instagram_business_account_id' => $igAccount['id']],
                        [
                            'username' => $igAccount['username'],
                            'display_name' => $igAccount['name'] ?? $igAccount['username'],
                            'avatar_color' => $this->generateRandomColor(),
                            'access_token' => $userAccessToken,
                            'token_expires_at' => now()->addSeconds($expiresIn),
                            'instagram_user_id' => $igAccount['id'],
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
                    Log::error('Failed to import Instagram account', [
                        'username' => $igAccount['username'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // If there are more accounts to import, queue background jobs
            if (!empty($firstBatchResult['next_url'])) {
                Log::info('Queueing background import for remaining accounts', [
                    'first_batch_imported' => $importedCount,
                    'has_more_pages' => true
                ]);

                // Queue background import starting from the next page
                \App\Jobs\ImportInstagramAccountsBatch::dispatch(
                    $userAccessToken,
                    $firstBatchResult['next_url'],
                    2, // batch number (first batch was 1)
                    15  // max 15 additional batches (15 * 25 = 375 more accounts)
                )->delay(now()->addSeconds(10)); // Start after 10 seconds

                $message = "Successfully connected {$importedCount} Instagram account(s) immediately! Additional accounts are being imported in the background. Check back in a few minutes to see all your accounts.";
            } else {
                $message = "Successfully connected {$importedCount} Instagram account(s)!";
            }
            
            if (!empty($errors)) {
                $message .= " Some errors occurred: " . implode(', ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " and " . (count($errors) - 3) . " more...";
                }
            }

            return redirect()->route('dashboard')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Instagram callback error: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'An error occurred while connecting Instagram accounts');
        }
    }

    /**
     * Disconnect Instagram account
     */
    public function disconnect(InstagramAccount $account)
    {
        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'is_active' => false
        ]);

        return redirect()->route('dashboard')->with('success', 'Instagram account disconnected successfully');
    }

    /**
     * Refresh Instagram token
     */
    public function refreshToken(InstagramAccount $account)
    {
        if (!$account->access_token) {
            return redirect()->route('dashboard')->with('error', 'No access token found for this account');
        }

        $refreshResponse = $this->instagramService->refreshAccessToken($account->access_token);

        if ($refreshResponse && isset($refreshResponse['access_token'])) {
            $expiresAt = now()->addSeconds($refreshResponse['expires_in'] ?? 5184000);
            
            $account->update([
                'access_token' => $refreshResponse['access_token'],
                'token_expires_at' => $expiresAt,
                'last_sync_at' => now()
            ]);

            return redirect()->route('dashboard')->with('success', 'Instagram token refreshed successfully');
        }

        return redirect()->route('dashboard')->with('error', 'Failed to refresh Instagram token');
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
