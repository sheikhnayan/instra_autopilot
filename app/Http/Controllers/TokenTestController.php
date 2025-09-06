<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Services\InstagramApiService;
use App\Services\TokenRefreshService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenTestController extends Controller
{
    /**
     * Test token refresh functionality
     */
    public function testTokenRefresh(Request $request)
    {
        $accountId = $request->get('account_id');
        
        if (!$accountId) {
            return response()->json([
                'error' => 'Please provide account_id parameter'
            ], 400);
        }
        
        $account = InstagramAccount::find($accountId);
        
        if (!$account) {
            return response()->json([
                'error' => 'Instagram account not found'
            ], 404);
        }
        
        $instagramService = new InstagramApiService();
        $tokenRefreshService = new TokenRefreshService($instagramService);
        
        try {
            // Check if token needs refresh
            $needsRefresh = $tokenRefreshService->needsRefresh($account);
            
            $response = [
                'account_id' => $account->id,
                'username' => $account->username,
                'needs_refresh' => $needsRefresh,
                'current_token_expires_at' => $account->token_expires_at,
                'page_token_expires_at' => $account->page_token_expires_at,
                'token_last_refreshed_at' => $account->token_last_refreshed_at,
            ];
            
            if ($request->get('force_refresh') === 'true' || $needsRefresh) {
                Log::info('Testing token refresh for account', ['account_id' => $account->id]);
                
                $refreshResult = $tokenRefreshService->refreshAccountToken($account);
                
                if ($refreshResult) {
                    // Reload account to get fresh data
                    $account->refresh();
                    
                    $response['refresh_attempted'] = true;
                    $response['refresh_successful'] = true;
                    $response['new_token_expires_at'] = $account->token_expires_at;
                    $response['new_page_token_expires_at'] = $account->page_token_expires_at;
                    $response['new_token_last_refreshed_at'] = $account->token_last_refreshed_at;
                } else {
                    $response['refresh_attempted'] = true;
                    $response['refresh_successful'] = false;
                    $response['error'] = 'Token refresh failed';
                }
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('Token test error', [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Test failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get token status for all accounts
     */
    public function getTokenStatus()
    {
        $instagramService = new InstagramApiService();
        $tokenRefreshService = new TokenRefreshService($instagramService);
        
        try {
            $statusSummary = $tokenRefreshService->getTokenStatusSummary();
            
            return response()->json($statusSummary);
            
        } catch (\Exception $e) {
            Log::error('Token status check error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Status check failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test automatic token refresh during posting simulation
     */
    public function testPostingWithRefresh(Request $request)
    {
        $accountId = $request->get('account_id');
        
        if (!$accountId) {
            return response()->json([
                'error' => 'Please provide account_id parameter'
            ], 400);
        }
        
        $account = InstagramAccount::find($accountId);
        
        if (!$account) {
            return response()->json([
                'error' => 'Instagram account not found'
            ], 404);
        }
        
        $instagramService = new InstagramApiService();
        $tokenRefreshService = new TokenRefreshService($instagramService);
        
        try {
            // Simulate ensuring we have a valid token before posting
            $ensureResult = $tokenRefreshService->ensureValidToken($account);
            
            $response = [
                'account_id' => $account->id,
                'username' => $account->username,
                'token_was_valid' => $ensureResult,
                'simulation' => 'posting_preparation',
                'current_token_expires_at' => $account->token_expires_at,
                'page_token_expires_at' => $account->page_token_expires_at,
            ];
            
            if ($ensureResult) {
                $response['status'] = 'ready_to_post';
                $response['message'] = 'Account tokens are valid and ready for posting';
            } else {
                $response['status'] = 'not_ready';
                $response['message'] = 'Account tokens could not be validated/refreshed';
            }
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            Log::error('Posting simulation test error', [
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Simulation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
