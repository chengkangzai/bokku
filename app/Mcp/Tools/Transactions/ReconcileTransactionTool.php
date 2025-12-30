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
class ReconcileTransactionTool extends Tool
{
    protected string $description = 'Mark a transaction as reconciled or unreconciled.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'is_reconciled' => ['nullable', 'boolean'],
        ], [
            'id.required' => 'Please specify the transaction ID.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        $isReconciled = $validated['is_reconciled'] ?? true;
        $transaction->update(['is_reconciled' => $isReconciled]);

        $status = $isReconciled ? 'reconciled' : 'unreconciled';

        return Response::structured([
            'message' => "Transaction marked as {$status}.",
            'transaction' => [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'is_reconciled' => $transaction->is_reconciled,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction ID')
                ->required(),
            'is_reconciled' => $schema->boolean()
                ->description('Set to true to reconcile, false to unreconcile')
                ->default(true),
        ];
    }
}
