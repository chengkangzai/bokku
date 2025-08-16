<?php

namespace App\Console\Commands;

use Exception;
use Log;
use App\Models\RecurringTransaction;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:process 
                            {--dry-run : Preview which transactions would be created without actually creating them}
                            {--user= : Process only for a specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due recurring transactions and create actual transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');

        $this->info('Processing recurring transactions...');

        // Get all due recurring transactions
        $query = RecurringTransaction::due()
            ->where('auto_process', true);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $dueTransactions = $query->get();

        if ($dueTransactions->isEmpty()) {
            $this->info('No recurring transactions are due.');

            return Command::SUCCESS;
        }

        $this->info("Found {$dueTransactions->count()} due recurring transaction(s).");

        $processed = 0;
        $failed = 0;

        foreach ($dueTransactions as $recurring) {
            try {
                if ($dryRun) {
                    $this->line("Would process: {$recurring->description} - RM {$recurring->amount}");
                    $this->line("  Next date would be: {$recurring->calculateNextDate()->format('Y-m-d')}");
                } else {
                    $transaction = $recurring->generateTransaction();

                    if ($transaction) {
                        $this->info("✓ Created transaction: {$recurring->description} - RM {$recurring->amount}");
                        $this->line("  Next occurrence: {$recurring->next_date->format('Y-m-d')}");
                        $processed++;
                    } else {
                        $this->warn("⚠ Skipped (not due): {$recurring->description}");
                    }
                }
            } catch (Exception $e) {
                $this->error("✗ Failed to process: {$recurring->description}");
                $this->error("  Error: {$e->getMessage()}");
                $failed++;

                // Log the error for debugging
                Log::error('Failed to process recurring transaction', [
                    'recurring_id' => $recurring->id,
                    'description' => $recurring->description,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if (! $dryRun) {
            $this->newLine();
            $this->info('Summary:');
            $this->info("  Processed: {$processed}");

            if ($failed > 0) {
                $this->error("  Failed: {$failed}");

                return Command::FAILURE;
            }
        } else {
            $this->newLine();
            $this->info('Dry run complete - no transactions were created.');
        }

        return Command::SUCCESS;
    }
}
