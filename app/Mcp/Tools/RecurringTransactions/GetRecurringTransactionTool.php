<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetRecurringTransactionTool extends Tool
{
    protected string $description = 'Get a recurring transaction schedule by ID, including its next five occurrences.';

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

        return Response::structured([
            'recurring_transaction' => $this->service->detail($recurring),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The recurring transaction ID')
                ->required(),
        ];
    }
}
