<?php

namespace App\Mcp\Tools\RecurringTransactions;

use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use App\Services\RecurringTransactionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRecurringTransactionsTool extends Tool
{
    protected string $description = 'List recurring transaction schedules for the authenticated user. Filter by status, type, frequency, account, or by what is due or upcoming.';

    public function __construct(private RecurringTransactionService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', Rule::enum(TransactionType::class)],
            'frequency' => ['nullable', 'string', Rule::in(RecurringTransactionService::FREQUENCIES)],
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'due_only' => ['nullable', 'boolean'],
            'upcoming_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $query = RecurringTransaction::where('user_id', $request->user()->id)
            ->with(['account', 'toAccount', 'category'])
            ->orderBy('next_date');

        if (isset($validated['is_active'])) {
            $query->where('is_active', $validated['is_active']);
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['frequency'])) {
            $query->where('frequency', $validated['frequency']);
        }

        if (isset($validated['account_id'])) {
            $query->where(function ($sub) use ($validated) {
                $sub->where('account_id', $validated['account_id'])
                    ->orWhere('to_account_id', $validated['account_id']);
            });
        }

        if (isset($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if ($validated['due_only'] ?? false) {
            $query->due();
        }

        if (isset($validated['upcoming_days'])) {
            $query->upcoming($validated['upcoming_days']);
        }

        $recurring = $query->get()->map(fn (RecurringTransaction $item) => $this->service->summarize($item));

        return Response::structured([
            'recurring_transactions' => $recurring->toArray(),
            'count' => $recurring->count(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_active' => $schema->boolean()
                ->description('Filter by active status'),
            'type' => $schema->string()
                ->enum(['income', 'expense', 'transfer'])
                ->description('Filter by transaction type'),
            'frequency' => $schema->string()
                ->enum(RecurringTransactionService::FREQUENCIES)
                ->description('Filter by recurrence frequency'),
            'account_id' => $schema->integer()
                ->description('Filter by account ID (matches source or destination account)'),
            'category_id' => $schema->integer()
                ->description('Filter by category ID'),
            'due_only' => $schema->boolean()
                ->description('Only return active schedules that are due now')
                ->default(false),
            'upcoming_days' => $schema->integer()
                ->description('Only return active schedules due within this many days'),
        ];
    }
}
