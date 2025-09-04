<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledPostsJob;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessScheduledPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instagram:process-scheduled-posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled Instagram posts based on their intervals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process scheduled posts...');

        // Get all active schedules that are ready to execute
        $schedules = Schedule::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_execution_at')
                    ->orWhere('next_execution_at', '<=', Carbon::now());
            })
            ->with(['instagramAccount', 'contentContainer.posts'])
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules ready for execution.');
            return;
        }

        $this->info("Found {$schedules->count()} schedule(s) ready for execution.");

        foreach ($schedules as $schedule) {
            try {
                $this->info("Processing schedule ID: {$schedule->id} for container: {$schedule->contentContainer->name}");
                
                // Dispatch the job to process this schedule
                ProcessScheduledPostsJob::dispatch($schedule);
                
                $this->info("✓ Schedule ID: {$schedule->id} dispatched successfully");
                
            } catch (\Exception $e) {
                $this->error("✗ Failed to process schedule ID: {$schedule->id} - Error: {$e->getMessage()}");
            }
        }

        $this->info('Finished processing scheduled posts.');
    }
}
