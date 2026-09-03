<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PauseRecurringTransactionTool extends Tool
{
    protected string $description = 'Pause a recurring transaction schedule so it stops generating transactions. The schedule and its next date are preserved.';

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

        if (! $recurring->is_active) {
            return Response::error("Recurring transaction '{$recurring->description}' is already paused.");
        }

        $recurring->pause();

        return Response::structured([
            'message' => "Recurring transaction '{$recurring->description}' paused.",
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
                ->description('The recurring transaction ID to pause')
                ->required(),
        ];
    }
}
