<?php

namespace App\Mcp\Tools\Transactions;

use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTransactionTool extends Tool
{
    protected string $description = 'Get details of a specific transaction by ID.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Please specify the transaction ID.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->with(['account', 'category', 'payee', 'fromAccount', 'toAccount', 'tags'])
            ->find($validated['id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        return Response::structured([
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'formatted_amount' => $transaction->formatted_amount,
                'description' => $transaction->description,
                'date' => $transaction->date->toDateString(),
                'account' => $transaction->account ? [
                    'id' => $transaction->account->id,
                    'name' => $transaction->account->name,
                ] : null,
                'category' => $transaction->category ? [
                    'id' => $transaction->category->id,
                    'name' => $transaction->category->name,
                ] : null,
                'payee' => $transaction->payee ? [
                    'id' => $transaction->payee->id,
                    'name' => $transaction->payee->name,
                ] : null,
                'from_account' => $transaction->fromAccount ? [
                    'id' => $transaction->fromAccount->id,
                    'name' => $transaction->fromAccount->name,
                ] : null,
                'to_account' => $transaction->toAccount ? [
                    'id' => $transaction->toAccount->id,
                    'name' => $transaction->toAccount->name,
                ] : null,
                'reference' => $transaction->reference,
                'notes' => $transaction->notes,
                'is_reconciled' => $transaction->is_reconciled,
                'tags' => $transaction->getUserTags()->pluck('name')->toArray(),
                'created_at' => $transaction->created_at->toIso8601String(),
                'updated_at' => $transaction->updated_at->toIso8601String(),
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
        ];
    }
}
