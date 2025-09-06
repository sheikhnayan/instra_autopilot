<?php

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Services\InstagramApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshInstagramTokens extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'instagram:refresh-tokens {--account_id= : Specific account ID to refresh} {--all : Refresh all accounts} {--dry-run : Show what would be refreshed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Refresh Instagram access tokens for accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $instagramService = new InstagramApiService();
        
        if ($this->option('account_id')) {
            // Refresh specific account
            $account = InstagramAccount::find($this->option('account_id'));
            if (!$account) {
                $this->error("Account with ID {$this->option('account_id')} not found.");
                return 1;
            }
            
            $this->refreshAccount($account, $instagramService);
            
        } elseif ($this->option('all')) {
            // Refresh all active accounts
            $accounts = InstagramAccount::where('is_active', true)->get();
            
            $this->info("Found {$accounts->count()} active Instagram accounts.");
            
            foreach ($accounts as $account) {
                $this->refreshAccount($account, $instagramService);
            }
            
        } else {
            // Refresh accounts with tokens expiring within 7 days
            $expiringAccounts = InstagramAccount::where('is_active', true)
                ->where('token_expires_at', '<=', now()->addDays(7))
                ->get();
                
            $this->info("Found {$expiringAccounts->count()} accounts with tokens expiring within 7 days.");
            
            foreach ($expiringAccounts as $account) {
                $this->refreshAccount($account, $instagramService);
            }
        }
        
        $this->info('Token refresh process completed.');
        return 0;
    }

    /**
     * Refresh tokens for a specific account
     */
    private function refreshAccount(InstagramAccount $account, InstagramApiService $instagramService)
    {
        $this->info("Processing account: {$account->username} (ID: {$account->id})");
        
        if ($this->option('dry-run')) {
            $this->line("  [DRY RUN] Would refresh tokens for this account");
            $this->line("  Current expiry: " . ($account->token_expires_at ? $account->token_expires_at->format('Y-m-d H:i:s') : 'Unknown'));
            return;
        }
        
        try {
            // Validate current token
            if ($instagramService->validateToken($account->access_token)) {
                $this->line("  ✓ Current token is still valid");
                
                if (!$this->option('all')) {
                    $this->line("  Skipping refresh (use --all to force refresh valid tokens)");
                    return;
                }
            } else {
                $this->line("  ⚠ Current token is invalid or expired");
            }
            
            // Attempt refresh
            $refreshResult = $instagramService->refreshAccessToken($account->access_token);
            
            if ($refreshResult && isset($refreshResult['access_token'])) {
                $expiresAt = now()->addSeconds($refreshResult['expires_in'] ?? 5183999);
                
                $account->update([
                    'access_token' => $refreshResult['access_token'],
                    'token_expires_at' => $expiresAt
                ]);
                
                $this->line("  ✓ User access token refreshed successfully");
                $this->line("  New expiry: " . $expiresAt->format('Y-m-d H:i:s'));
                
                // Try to refresh Facebook Page access token
                $pageTokenResult = $instagramService->refreshPageAccessToken($refreshResult['access_token']);
                
                if ($pageTokenResult && isset($pageTokenResult['data'])) {
                    foreach ($pageTokenResult['data'] as $page) {
                        if (isset($page['instagram_business_account']['id']) && 
                            $page['instagram_business_account']['id'] === $account->instagram_business_account_id) {
                            
                            $account->update([
                                'facebook_page_access_token' => $page['access_token']
                            ]);
                            
                            $this->line("  ✓ Facebook Page access token refreshed successfully");
                            break;
                        }
                    }
                } else {
                    $this->line("  ⚠ Failed to refresh Facebook Page access token");
                }
                
                Log::info('Manual token refresh successful', [
                    'account_id' => $account->id,
                    'username' => $account->username,
                    'expires_at' => $expiresAt
                ]);
                
            } else {
                $this->error("  ✗ Failed to refresh tokens");
                
                Log::error('Manual token refresh failed', [
                    'account_id' => $account->id,
                    'username' => $account->username,
                    'refresh_result' => $refreshResult
                ]);
            }
            
        } catch (\Exception $e) {
            $this->error("  ✗ Exception during token refresh: " . $e->getMessage());
            
            Log::error('Manual token refresh exception', [
                'account_id' => $account->id,
                'username' => $account->username,
                'error' => $e->getMessage()
            ]);
        }
        
        $this->line(''); // Empty line for readability
    }
}
