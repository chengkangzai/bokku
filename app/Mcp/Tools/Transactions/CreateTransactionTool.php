<?php

namespace App\Mcp\Tools\Transactions;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class CreateTransactionTool extends Tool
{
    protected string $description = 'Create a new transaction (income, expense, or transfer).';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', 'integer'],
            'from_account_id' => ['nullable', 'integer'],
            'to_account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'payee_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_reconciled' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ], [
            'type.required' => 'Please specify the transaction type (income, expense, or transfer).',
            'amount.required' => 'Please specify the transaction amount.',
            'description.required' => 'Please provide a description for the transaction.',
            'date.required' => 'Please specify the transaction date.',
        ]);

        $type = TransactionType::from($validated['type']);

        if ($type === TransactionType::Transfer) {
            if (! isset($validated['from_account_id']) || ! isset($validated['to_account_id'])) {
                return Response::error('Transfer transactions require both from_account_id and to_account_id.');
            }

            $fromAccount = Account::where('user_id', $request->user()->id)
                ->find($validated['from_account_id']);
            $toAccount = Account::where('user_id', $request->user()->id)
                ->find($validated['to_account_id']);

            if (! $fromAccount || ! $toAccount) {
                return Response::error('One or both accounts not found or access denied.');
            }

            if ($fromAccount->id === $toAccount->id) {
                return Response::error('Cannot transfer to the same account.');
            }
        } else {
            if (! isset($validated['account_id'])) {
                return Response::error('Income and expense transactions require an account_id.');
            }

            $account = Account::where('user_id', $request->user()->id)
                ->find($validated['account_id']);

            if (! $account) {
                return Response::error('Account not found or access denied.');
            }
        }

        if (isset($validated['category_id'])) {
            $category = Category::where('user_id', $request->user()->id)
                ->find($validated['category_id']);

            if (! $category) {
                return Response::error('Category not found or access denied.');
            }
        }

        $transactionData = [
            'user_id' => $request->user()->id,
            'type' => $type,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'date' => $validated['date'],
            'category_id' => $validated['category_id'] ?? null,
            'payee_id' => $validated['payee_id'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_reconciled' => $validated['is_reconciled'] ?? false,
        ];

        if ($type === TransactionType::Transfer) {
            $transactionData['from_account_id'] = $validated['from_account_id'];
            $transactionData['to_account_id'] = $validated['to_account_id'];
            $transactionData['account_id'] = $validated['from_account_id'];
        } else {
            $transactionData['account_id'] = $validated['account_id'];
            $transactionData['from_account_id'] = null;
            $transactionData['to_account_id'] = null;
        }

        $transaction = Transaction::create($transactionData);

        if (isset($validated['tags'])) {
            $transaction->syncUserTags($validated['tags']);
        }

        return Response::structured([
            'message' => 'Transaction created successfully.',
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
            'type' => $schema->string()
                ->enum(['income', 'expense', 'transfer'])
                ->description('The transaction type')
                ->required(),
            'amount' => $schema->number()
                ->description('The transaction amount in decimal format (e.g., 150.50)')
                ->required(),
            'description' => $schema->string()
                ->description('Description of the transaction')
                ->required(),
            'date' => $schema->string()
                ->description('The transaction date (YYYY-MM-DD)')
                ->required(),
            'account_id' => $schema->integer()
                ->description('The account ID (required for income/expense, not for transfers)'),
            'from_account_id' => $schema->integer()
                ->description('Source account ID (required for transfers)'),
            'to_account_id' => $schema->integer()
                ->description('Destination account ID (required for transfers)'),
            'category_id' => $schema->integer()
                ->description('Optional category ID'),
            'payee_id' => $schema->integer()
                ->description('Optional payee ID'),
            'reference' => $schema->string()
                ->description('Optional reference number'),
            'notes' => $schema->string()
                ->description('Optional notes'),
            'is_reconciled' => $schema->boolean()
                ->description('Whether the transaction is reconciled')
                ->default(false),
            'tags' => $schema->array()
                ->description('Optional array of tag names to attach to the transaction'),
        ];
    }
}
