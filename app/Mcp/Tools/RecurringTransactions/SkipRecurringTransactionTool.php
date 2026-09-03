<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class SkipRecurringTransactionTool extends Tool
{
    protected string $description = 'Skip the next occurrence of a recurring transaction schedule by advancing it to the following date. No transaction is created.';

    public function __construct(private RecurringTransactionService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Recurring transaction ID is required.',
        ]);

        $recurring = RecurringTransaction::where('user_id', $request->user()->id)
            ->with(['account', 'toAccount', 'category'])
            ->find($validated['id']);

        if (! $recurring) {
            return Response::error('Recurring transaction not found or access denied.');
        }

        $skippedDate = $recurring->next_date->toDateString();
        $recurring->skipOnce();

        return Response::structured([
            'message' => "Skipped the {$skippedDate} occurrence of '{$recurring->description}'.",
            'skipped_date' => $skippedDate,
            'recurring_transaction' => $this->service->summarize($recurring),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The recurring transaction ID whose next occurrence should be skipped')
                ->required(),
        ];
    }
}
