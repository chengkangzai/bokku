<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class DeleteTransactionTool extends Tool
{
    protected string $description = 'Delete a transaction.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Please specify the transaction ID to delete.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        $transactionDescription = $transaction->description;
        $transaction->delete();

        return Response::structured([
            'message' => "Transaction '{$transactionDescription}' deleted successfully.",
            'deleted' => true,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The transaction ID to delete')
                ->required(),
        ];
    }
}
