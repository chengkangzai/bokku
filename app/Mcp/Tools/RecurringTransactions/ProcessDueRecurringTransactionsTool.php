<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Models\RecurringTransaction;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Log;

class ProcessDueRecurringTransactionsTool extends Tool
{
    protected string $description = 'Generate real transactions from recurring schedules that are due now, and advance each schedule to its next date. Use dry_run first to preview. This is the on-demand equivalent of the daily scheduler, and is the only way to run schedules with auto_process disabled.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'dry_run' => ['nullable', 'boolean'],
            'auto_process_only' => ['nullable', 'boolean'],
        ]);

        $dryRun = $validated['dry_run'] ?? false;

        $query = RecurringTransaction::where('user_id', $request->user()->id)
            ->with(['account', 'category'])
            ->due();

        if (isset($validated['ids'])) {
            $query->whereIn('id', $validated['ids']);
        }

        if ($validated['auto_process_only'] ?? false) {
            $query->where('auto_process', true);
        }

        $due = $query->get();

        if ($due->isEmpty()) {
            return Response::structured([
                'message' => 'No recurring transactions are due.',
                'dry_run' => $dryRun,
                'processed_count' => 0,
                'processed' => [],
                'failed' => [],
            ]);
        }

        $processed = [];
        $failed = [];

        foreach ($due as $recurring) {
            if ($dryRun) {
                $processed[] = [
                    'recurring_transaction_id' => $recurring->id,
                    'description' => $recurring->description,
                    'amount' => $recurring->amount,
                    'would_use_date' => $recurring->next_date->toDateString(),
                    'next_date_after_processing' => $recurring->calculateNextDate()->toDateString(),
                ];

                continue;
            }

            try {
                $transaction = $recurring->generateTransaction();
            } catch (Exception $exception) {
                Log::error('Failed to process recurring transaction via MCP', [
                    'recurring_id' => $recurring->id,
                    'error' => $exception->getMessage(),
                ]);

                $failed[] = [
                    'recurring_transaction_id' => $recurring->id,
                    'description' => $recurring->description,
                    'error' => $exception->getMessage(),
                ];

                continue;
            }

            if (! $transaction) {
                $failed[] = [
                    'recurring_transaction_id' => $recurring->id,
                    'description' => $recurring->description,
                    'error' => 'Schedule was no longer due when processed.',
                ];

                continue;
            }

            $processed[] = [
                'recurring_transaction_id' => $recurring->id,
                'transaction_id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => $transaction->amount,
                'date' => $transaction->date->toDateString(),
                'next_date' => $recurring->next_date->toDateString(),
            ];
        }

        $count = count($processed);

        return Response::structured([
            'message' => $dryRun
                ? "{$count} recurring transaction(s) would be processed. Nothing was created."
                : "{$count} transaction(s) generated from recurring schedules.",
            'dry_run' => $dryRun,
            'processed_count' => $count,
            'processed' => $processed,
            'failed' => $failed,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema->array()
                ->description('Optional list of recurring transaction IDs to process. Defaults to every due schedule.'),
            'dry_run' => $schema->boolean()
                ->description('Preview what would be generated without creating anything')
                ->default(false),
            'auto_process_only' => $schema->boolean()
                ->description('Only process schedules with auto_process enabled, matching the daily scheduler')
                ->default(false),
        ];
    }
}
