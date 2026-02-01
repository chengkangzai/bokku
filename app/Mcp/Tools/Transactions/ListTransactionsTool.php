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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTransactionsTool extends Tool
{
    protected string $description = 'List transactions with optional filtering by account, date range, type, and pagination.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::enum(TransactionType::class)],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'is_reconciled' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Transaction::where('user_id', $request->user()->id)
            ->with(['account', 'category', 'tags'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (isset($validated['account_id'])) {
            $query->where('account_id', $validated['account_id']);
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['from_date'])) {
            $query->whereDate('date', '>=', $validated['from_date']);
        }

        if (isset($validated['to_date'])) {
            $query->whereDate('date', '<=', $validated['to_date']);
        }

        if (isset($validated['is_reconciled'])) {
            $query->where('is_reconciled', $validated['is_reconciled']);
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if (isset($validated['tags'])) {
            $query->withAnyUserTags($validated['tags'], $request->user()->id);
        }

        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        $transactions = $query->paginate($perPage, ['*'], 'page', $page);

        return Response::structured([
            'transactions' => collect($transactions->items())->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type->value,
                'amount' => $transaction->amount,
                'formatted_amount' => $transaction->formatted_amount,
                'description' => $transaction->description,
                'date' => $transaction->date->toDateString(),
                'account' => $transaction->account?->name,
                'category' => $transaction->category?->name,
                'is_reconciled' => $transaction->is_reconciled,
                'tags' => $transaction->getUserTags()->pluck('name')->toArray(),
            ])->toArray(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()
                ->description('Filter by account ID'),
            'category_id' => $schema->integer()
                ->description('Filter by category ID'),
            'type' => $schema->string()
                ->enum(['income', 'expense', 'transfer'])
                ->description('Filter by transaction type'),
            'from_date' => $schema->string()
                ->description('Filter transactions from this date (YYYY-MM-DD)'),
            'to_date' => $schema->string()
                ->description('Filter transactions until this date (YYYY-MM-DD)'),
            'is_reconciled' => $schema->boolean()
                ->description('Filter by reconciliation status'),
            'search' => $schema->string()
                ->description('Search in description, notes, and reference'),
            'tags' => $schema->array()
                ->description('Filter by any of these tag names (OR logic)'),
            'per_page' => $schema->integer()
                ->description('Number of transactions per page (default: 20, max: 100)')
                ->default(20),
            'page' => $schema->integer()
                ->description('Page number for pagination')
                ->default(1),
        ];
    }
}
