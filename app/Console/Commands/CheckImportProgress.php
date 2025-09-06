<?php

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckImportProgress extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:check-import-progress';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the progress of Instagram account background import';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Instagram Account Import Progress:');
        $this->line('');

        // Get total accounts
        $totalAccounts = InstagramAccount::where('is_active', true)->count();
        
        // Get recently imported accounts (last 24 hours)
        $recentAccounts = InstagramAccount::where('is_active', true)
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->get();

        $this->info("Total Active Accounts: {$totalAccounts}");
        
        if ($recentAccounts->count() > 0) {
            $this->info("Recently Imported (last 24h): {$recentAccounts->count()}");
            $this->line('');
            
            $headers = ['Username', 'Imported At', 'Time Ago'];
            $rows = [];
            
            foreach ($recentAccounts->take(10) as $account) {
                $rows[] = [
                    $account->username,
                    $account->created_at->format('Y-m-d H:i:s'),
                    $account->created_at->diffForHumans()
                ];
            }
            
            $this->table($headers, $rows);
            
            if ($recentAccounts->count() > 10) {
                $this->info("... and " . ($recentAccounts->count() - 10) . " more recently imported accounts.");
            }
        } else {
            $this->info("No accounts imported in the last 24 hours.");
        }

        // Check if there are any background import jobs running
        $this->line('');
        $this->info('To check if background imports are still running, check your queue:');
        $this->comment('php artisan queue:work --stop-when-empty');
        
        $this->line('');
        $this->info('If you want to manually trigger more imports, you can reconnect your Instagram account.');
    }
}
