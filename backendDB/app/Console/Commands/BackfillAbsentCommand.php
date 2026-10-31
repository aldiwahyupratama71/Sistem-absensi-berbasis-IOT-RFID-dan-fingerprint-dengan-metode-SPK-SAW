<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class BackfillAbsentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:backfill {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing ABSENT records for a date range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fromStr = $this->option('from');
        $toStr = $this->option('to');

        if (!$fromStr || !$toStr) {
            $this->error('Both --from and --to options are required.');
            return;
        }

        $from = Carbon::parse($fromStr);
        $to = Carbon::parse($toStr);

        if ($from->gt($to)) {
            $this->error('--from date must be before or equal to --to date.');
            return;
        }

        $this->info("Backfilling from {$fromStr} to {$toStr}...");

        for ($date = $from; $date->lte($to); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $this->line("Processing {$dateStr}...");
            
            Artisan::call('attendance:mark-absent', [
                '--date' => $dateStr
            ], $this->getOutput());
        }

        $this->info("Backfill completed.");
    }
}
