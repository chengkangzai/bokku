<?php

namespace App\Mcp\Tools\TransactionRules;

use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Services\TransactionRuleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class ApplyTransactionRulesTool extends Tool
{
    protected string $description = 'Apply transaction rules to transactions that already exist. Rules normally only run on newly created transactions, so use this to backfill after adding or changing a rule. Use dry_run first to preview.';

    public function __construct(private TransactionRuleService $service) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'rule_id' => ['nullable', 'integer'],
            'transaction_ids' => ['nullable', 'array'],
            'transaction_ids.*' => ['integer'],
            'only_uncategorized' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $dryRun = $validated['dry_run'] ?? false;
        $limit = $validated['limit'] ?? 100;

        $rule = null;

        if (isset($validated['rule_id'])) {
            $rule = TransactionRule::where('user_id', $user->id)->find($validated['rule_id']);

            if (! $rule) {
                return Response::error('Transaction rule not found or access denied.');
            }

            if (! $rule->is_active) {
                return Response::error("Transaction rule '{$rule->name}' is inactive. Activate it before applying.");
            }
        }

        $rules = $rule
            ? collect([$rule])
            : TransactionRule::where('user_id', $user->id)->active()->get();

        if ($rules->isEmpty()) {
            return Response::error('No active transaction rules to apply.');
        }

        $query = Transaction::where('user_id', $user->id)->orderBy('date', 'desc');

        if (isset($validated['transaction_ids'])) {
            $query->whereIn('id', $validated['transaction_ids']);
        }

        if ($validated['only_uncategorized'] ?? false) {
            $query->whereNull('category_id');
        }

        if ($rule && $rule->apply_to !== 'all') {
            $query->where('type', $rule->apply_to);
        }

        $transactions = $query->limit($limit)->get();

        $affected = [];

        foreach ($transactions as $transaction) {
            $matching = $rules->filter(fn (TransactionRule $candidate) => $candidate->matches($transaction));

            if ($matching->isEmpty()) {
                continue;
            }

            $before = $this->snapshot($transaction);

            if (! $dryRun) {
                $rule
                    ? $rule->apply($transaction)
                    : TransactionRule::applyRules($transaction);

                $transaction->refresh();
            }

            $affected[] = [
                'transaction_id' => $transaction->id,
                'description' => $transaction->description,
                'date' => $transaction->date->toDateString(),
                'matched_rules' => $matching->map(fn (TransactionRule $matched) => [
                    'id' => $matched->id,
                    'name' => $matched->name,
                ])->values()->all(),
                'before' => $before,
                'after' => $dryRun ? null : $this->snapshot($transaction),
            ];
        }

        $count = count($affected);

        return Response::structured([
            'message' => $dryRun
                ? "{$count} of {$transactions->count()} transaction(s) scanned would be updated. Nothing was changed."
                : "{$count} of {$transactions->count()} transaction(s) scanned were updated.",
            'dry_run' => $dryRun,
            'scanned_count' => $transactions->count(),
            'affected_count' => $count,
            'affected' => $affected,
        ]);
    }

    /**
     * @return array{category_id: ?int, account_id: int, notes: ?string, tags: array<int, string>}
     */
    private function snapshot(Transaction $transaction): array
    {
        return [
            'category_id' => $transaction->category_id,
            'account_id' => $transaction->account_id,
            'notes' => $transaction->notes,
            'tags' => $transaction->getUserTags()->pluck('name')->toArray(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'rule_id' => $schema->integer()
                ->description('Apply only this rule. Defaults to every active rule, in priority order.'),
            'transaction_ids' => $schema->array()
                ->description('Optional list of transaction IDs to limit the run to'),
            'only_uncategorized' => $schema->boolean()
                ->description('Only consider transactions that have no category yet')
                ->default(false),
            'limit' => $schema->integer()
                ->description('Maximum number of transactions to scan, newest first')
                ->default(100),
            'dry_run' => $schema->boolean()
                ->description('Preview the changes without writing them')
                ->default(false),
        ];
    }
}
