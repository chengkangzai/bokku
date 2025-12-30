<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class BulkReconcileTool extends Tool
{
    protected string $description = 'Reconcile or unreconcile multiple transactions at once.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer'],
            'is_reconciled' => ['nullable', 'boolean'],
        ], [
            'transaction_ids.required' => 'Please specify the transaction IDs to reconcile.',
            'transaction_ids.min' => 'Please specify at least one transaction ID.',
        ]);

        $isReconciled = $validated['is_reconciled'] ?? true;

        $count = Transaction::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['transaction_ids'])
            ->update(['is_reconciled' => $isReconciled]);

        $status = $isReconciled ? 'reconciled' : 'unreconciled';

        return Response::structured([
            'message' => "{$count} transaction(s) marked as {$status}.",
            'count' => $count,
            'is_reconciled' => $isReconciled,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transaction_ids' => $schema->array($schema->integer())
                ->description('Array of transaction IDs to reconcile')
                ->required(),
            'is_reconciled' => $schema->boolean()
                ->description('Set to true to reconcile, false to unreconcile')
                ->default(true),
        ];
    }
}
