<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateRecurringTransactionTool extends Tool
{
    protected string $description = 'Update a recurring transaction schedule. Only the fields you provide are changed.';

    public function __construct(private RecurringTransactionService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            ...RecurringTransactionService::rules(forUpdate: true),
        ], [
            'id.required' => 'Recurring transaction ID is required.',
        ]);

        $recurring = RecurringTransaction::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $recurring) {
            return Response::error('Recurring transaction not found or access denied.');
        }

        try {
            $this->service->update($recurring, array_filter(
                $validated,
                fn (string $key) => $key !== 'id',
                ARRAY_FILTER_USE_KEY,
            ));
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        $recurring->load(['account', 'toAccount', 'category']);

        return Response::structured([
            'message' => "Recurring transaction '{$recurring->description}' updated successfully.",
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
                ->description('The recurring transaction ID to update')
                ->required(),
            ...CreateRecurringTransactionTool::recurringSchema($schema, forUpdate: true),
        ];
    }
}
