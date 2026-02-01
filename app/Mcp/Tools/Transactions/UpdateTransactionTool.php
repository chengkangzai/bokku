<?php

namespace App\Mcp\Tools\Transactions;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateTransactionTool extends Tool
{
    protected string $description = 'Update an existing transaction.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'type' => ['nullable', 'string', Rule::enum(TransactionType::class)],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'category_id' => ['nullable', 'integer'],
            'payee_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_reconciled' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ], [
            'id.required' => 'Please specify the transaction ID to update.',
        ]);

        $transaction = Transaction::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $transaction) {
            return Response::error('Transaction not found or access denied.');
        }

        $updates = [];

        if (isset($validated['type'])) {
            $updates['type'] = $validated['type'];
        }

        if (isset($validated['amount'])) {
            $updates['amount'] = $validated['amount'];
        }

        if (isset($validated['description'])) {
            $updates['description'] = $validated['description'];
        }

        if (isset($validated['date'])) {
            $updates['date'] = $validated['date'];
        }

        if (array_key_exists('category_id', $validated)) {
            $updates['category_id'] = $validated['category_id'];
        }

        if (array_key_exists('payee_id', $validated)) {
            $updates['payee_id'] = $validated['payee_id'];
        }

        if (array_key_exists('reference', $validated)) {
            $updates['reference'] = $validated['reference'];
        }

        if (array_key_exists('notes', $validated)) {
            $updates['notes'] = $validated['notes'];
        }

        if (isset($validated['is_reconciled'])) {
            $updates['is_reconciled'] = $validated['is_reconciled'];
        }

        $transaction->update($updates);

        if (isset($validated['tags'])) {
            $transaction->syncUserTags($validated['tags']);
        }

        return Response::structured([
            'message' => 'Transaction updated successfully.',
            'transaction' => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'formatted_amount' => $transaction->formatted_amount,
                'description' => $transaction->description,
                'date' => $transaction->date->toDateString(),
                'is_reconciled' => $transaction->is_reconciled,
                'tags' => $transaction->getUserTags()->pluck('name')->toArray(),
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
                ->description('The transaction ID to update')
                ->required(),
            'type' => $schema->string()
                ->enum(['income', 'expense', 'transfer'])
                ->description('The new transaction type'),
            'amount' => $schema->number()
                ->description('The new amount in decimal format'),
            'description' => $schema->string()
                ->description('The new description'),
            'date' => $schema->string()
                ->description('The new date (YYYY-MM-DD)'),
            'category_id' => $schema->integer()
                ->description('The new category ID'),
            'payee_id' => $schema->integer()
                ->description('The new payee ID'),
            'reference' => $schema->string()
                ->description('The new reference number'),
            'notes' => $schema->string()
                ->description('New notes'),
            'is_reconciled' => $schema->boolean()
                ->description('Whether the transaction is reconciled'),
            'tags' => $schema->array()
                ->description('Optional array of tag names to sync with the transaction (replaces existing tags)'),
        ];
    }
}
