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
            dd($userAccessToken, $expiresIn);

            // Step 3: Get all Instagram accounts
            $instagramAccounts = $this->instagramService->getAllInstagramAccounts($userAccessToken);

            if (empty($instagramAccounts)) {
                return redirect()->route('dashboard')->with('error', 'No Instagram Business accounts found. Make sure your Instagram accounts are connected to Facebook Pages and converted to Business/Creator accounts.');
            }

            $importedCount = 0;
            $errors = [];

            // Step 4: Save each Instagram account
            foreach ($instagramAccounts as $accountData) {
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

            $message = "Successfully connected {$importedCount} Instagram account(s)!";
            
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
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
