<?php

namespace App\Jobs;

use App\Services\TokenRefreshService;
use App\Services\InstagramApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProactiveTokenRefreshJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting proactive token refresh job');

        $instagramService = new InstagramApiService();
        $tokenRefreshService = new TokenRefreshService($instagramService);

        // Get summary before refresh
        $beforeSummary = $tokenRefreshService->getTokenStatusSummary();
        
        Log::info('Token status before refresh', $beforeSummary);

        // Refresh expiring tokens
        $results = $tokenRefreshService->refreshExpiringSoon();

        // Get summary after refresh
        $afterSummary = $tokenRefreshService->getTokenStatusSummary();

        // Count successful refreshes
        $successCount = array_sum(array_filter($results));
        $totalAttempted = count($results);

        Log::info('Proactive token refresh completed', [
            'total_attempted' => $totalAttempted,
            'successful_refreshes' => $successCount,
            'failed_refreshes' => $totalAttempted - $successCount,
            'before_summary' => $beforeSummary,
            'after_summary' => $afterSummary,
            'results' => $results
        ]);

        // Log any accounts that still need attention
        if ($afterSummary['needs_refresh'] > 0) {
            Log::warning('Some accounts still need token refresh after job completion', [
                'accounts_needing_attention' => $afterSummary['needs_refresh']
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProactiveTokenRefreshJob failed', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
