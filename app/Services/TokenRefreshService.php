<?php

namespace App\Services;

use App\Models\InstagramAccount;
use Illuminate\Support\Facades\Log;

class TokenRefreshService
{
    private $instagramService;

    public function __construct(InstagramApiService $instagramService)
    {
        $this->instagramService = $instagramService;
    }

    /**
     * Check if an account's token needs refreshing (expires within 7 days)
     */
    public function needsRefresh(InstagramAccount $account): bool
    {
        if (!$account->token_expires_at) {
            return true; // No expiry date means we should refresh
        }

        return $account->token_expires_at->isBefore(now()->addDays(7));
    }

    /**
     * Proactively refresh tokens for accounts that need it
     */
    public function refreshExpiringSoon(): array
    {
        $results = [];
        
        $accounts = InstagramAccount::where('is_active', true)
            ->where(function ($query) {
                $query->where('token_expires_at', '<=', now()->addDays(7))
                      ->orWhereNull('token_expires_at');
            })
            ->get();

        Log::info('Proactive token refresh started', [
            'accounts_to_refresh' => $accounts->count()
        ]);

        foreach ($accounts as $account) {
            $results[$account->id] = $this->refreshAccountToken($account);
        }

        return $results;
    }

    /**
     * Refresh a specific account's token
     */
    public function refreshAccountToken(InstagramAccount $account): bool
    {
        try {
            Log::info('Refreshing token for account', [
                'account_id' => $account->id,
                'username' => $account->username,
                'current_expiry' => $account->token_expires_at
            ]);

            $refreshResult = $this->instagramService->refreshAccessToken($account->access_token);

            if ($refreshResult && isset($refreshResult['access_token'])) {
                $expiresAt = now()->addSeconds($refreshResult['expires_in'] ?? 5183999);
                
                $account->update([
                    'access_token' => $refreshResult['access_token'],
                    'token_expires_at' => $expiresAt
                ]);

                Log::info('User access token refreshed successfully', [
                    'account_id' => $account->id,
                    'new_expiry' => $expiresAt
                ]);

                // Try to refresh Facebook Page access token
                $pageTokenResult = $this->instagramService->refreshPageAccessToken($refreshResult['access_token']);

                if ($pageTokenResult && isset($pageTokenResult['data'])) {
                    foreach ($pageTokenResult['data'] as $page) {
                        if (isset($page['instagram_business_account']['id']) && 
                            $page['instagram_business_account']['id'] === $account->instagram_business_account_id) {
                            
                            $account->update([
                                'facebook_page_access_token' => $page['access_token']
                            ]);

                            Log::info('Facebook Page access token refreshed', [
                                'account_id' => $account->id,
                                'page_id' => $page['id']
                            ]);
                            
                            break;
                        }
                    }
                }

                return true;

            } else {
                Log::error('Failed to refresh access token', [
                    'account_id' => $account->id,
                    'refresh_result' => $refreshResult
                ]);

                // Mark account as needing re-authentication if refresh fails
                $account->update([
                    'is_active' => false,
                    'last_sync_at' => now()
                ]);

                return false;
            }

        } catch (\Exception $e) {
            Log::error('Exception during token refresh', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Validate and potentially refresh a token before use
     */
    public function ensureValidToken(InstagramAccount $account): bool
    {
        // First check if token is still valid
        if ($this->instagramService->validateToken($account->access_token)) {
            // Check if it needs proactive refresh (expires soon)
            if ($this->needsRefresh($account)) {
                Log::info('Token valid but expires soon, proactively refreshing', [
                    'account_id' => $account->id,
                    'expires_at' => $account->token_expires_at
                ]);
                
                return $this->refreshAccountToken($account);
            }
            
            return true; // Token is valid and doesn't need refresh yet
        }

        // Token is invalid, try to refresh
        Log::warning('Token invalid, attempting refresh', [
            'account_id' => $account->id,
            'username' => $account->username
        ]);

        return $this->refreshAccountToken($account);
    }

    /**
     * Get summary of account token statuses
     */
    public function getTokenStatusSummary(): array
    {
        $accounts = InstagramAccount::where('is_active', true)->get();
        
        $summary = [
            'total_accounts' => $accounts->count(),
            'valid_tokens' => 0,
            'expiring_soon' => 0,
            'expired_tokens' => 0,
            'invalid_tokens' => 0,
            'needs_refresh' => 0
        ];

        foreach ($accounts as $account) {
            if (!$account->token_expires_at) {
                $summary['invalid_tokens']++;
                $summary['needs_refresh']++;
                continue;
            }

            if ($account->token_expires_at->isPast()) {
                $summary['expired_tokens']++;
                $summary['needs_refresh']++;
            } elseif ($account->token_expires_at->isBefore(now()->addDays(7))) {
                $summary['expiring_soon']++;
                $summary['needs_refresh']++;
            } else {
                $summary['valid_tokens']++;
            }
        }

        return $summary;
    }
}
