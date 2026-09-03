<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ResumeRecurringTransactionTool extends Tool
{
    protected string $description = 'Resume a paused recurring transaction schedule. If its next date is already in the past it becomes due immediately.';

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

        if ($recurring->is_active) {
            return Response::error("Recurring transaction '{$recurring->description}' is already active.");
        }

        $recurring->resume();

        return Response::structured([
            'message' => "Recurring transaction '{$recurring->description}' resumed.",
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
                ->description('The recurring transaction ID to resume')
                ->required(),
        ];
    }
}
