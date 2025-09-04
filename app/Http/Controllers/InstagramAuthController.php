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
     * Handle Instagram callback
     */
    public function handleInstagramCallback(Request $request)
    {
        $code = $request->get('code');
        $error = $request->get('error');

        if ($error) {
            return redirect()->route('dashboard')->with('error', 'Instagram authorization failed: ' . $error);
        }

        if (!$code) {
            return redirect()->route('dashboard')->with('error', 'No authorization code received from Instagram');
        }

        try {
            // Step 1: Get short-lived access token
            $tokenResponse = $this->instagramService->getAccessToken($code);
            
            if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
                return redirect()->route('dashboard')->with('error', 'Failed to get access token from Instagram');
            }

            // Step 2: Exchange for long-lived token
            $longLivedTokenResponse = $this->instagramService->getLongLivedToken($tokenResponse['access_token']);
            
            if (!$longLivedTokenResponse || !isset($longLivedTokenResponse['access_token'])) {
                return redirect()->route('dashboard')->with('error', 'Failed to get long-lived token from Instagram');
            }

            // Step 3: Get user profile
            $userProfile = $this->instagramService->getUserProfile($longLivedTokenResponse['access_token']);
            
            if (!$userProfile || !isset($userProfile['id'])) {
                return redirect()->route('dashboard')->with('error', 'Failed to get user profile from Instagram');
            }

            // Step 4: Save or update Instagram account
            $expiresAt = now()->addSeconds($longLivedTokenResponse['expires_in'] ?? 5184000); // Default 60 days

            $instagramAccount = InstagramAccount::updateOrCreate(
                ['instagram_user_id' => $userProfile['id']],
                [
                    'username' => $userProfile['username'],
                    'display_name' => $userProfile['username'],
                    'avatar_color' => $this->generateRandomColor(),
                    'access_token' => $longLivedTokenResponse['access_token'],
                    'token_expires_at' => $expiresAt,
                    'account_type' => $userProfile['account_type'] ?? 'PERSONAL',
                    'media_count' => $userProfile['media_count'] ?? 0,
                    'last_sync_at' => now(),
                    'is_active' => true
                ]
            );

            return redirect()->route('dashboard')->with('success', 'Instagram account connected successfully!');

        } catch (\Exception $e) {
            Log::error('Instagram callback error: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'An error occurred while connecting your Instagram account');
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
