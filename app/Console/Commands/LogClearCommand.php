<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Laravel log files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logPath = storage_path('logs');
        
        if (!File::exists($logPath)) {
            $this->info('No log directory found.');
            return;
        }
        
        $files = File::files($logPath);
        $clearedCount = 0;
        
        foreach ($files as $file) {
            if ($file->getExtension() === 'log') {
                File::put($file->getPathname(), '');
                $clearedCount++;
            }
        }
        
        $this->info("Cleared {$clearedCount} log file(s).");
    }
}
