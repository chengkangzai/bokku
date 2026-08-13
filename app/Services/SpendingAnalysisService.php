<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpendingAnalysisService
{
    /**
     * @return array{
     *     income: float,
     *     expense: float,
     *     net: float,
     *     income_delta: ?float,
     *     expense_delta: ?float
     * }
     */
    public function summary(int $userId, CarbonImmutable $month): array
    {
        $current = $this->monthTotals($userId, $month);
        $previous = $this->monthTotals($userId, $month->subMonth());

        return [
            'income' => $current['income'],
            'expense' => $current['expense'],
            'net' => $current['income'] - $current['expense'],
            'income_delta' => $this->percentChange($current['income'], $previous['income']),
            'expense_delta' => $this->percentChange($current['expense'], $previous['expense']),
        ];
    }

    /**
     * Expense totals per category for the month, including uncategorized spending,
     * with the previous month's total per category for delta chips.
     *
     * @return Collection<int, object{
     *     id: ?int,
     *     name: string,
     *     color: ?string,
     *     count: int,
     *     total: float,
     *     previous: ?float
     * }>
     */
    public function breakdown(int $userId, CarbonImmutable $month): Collection
    {
        $current = $this->categoryTotals($userId, $month);
        $previous = $this->categoryTotals($userId, $month->subMonth())->keyBy('name');

        return $current->map(function (object $row) use ($previous): object {
            $row->previous = $previous->has($row->name)
                ? $previous->get($row->name)->total
                : null;

            return $row;
        })->values();
    }

    /**
     * Expense totals per tag for the month, colored from the default palette.
     *
     * @return Collection<int, object>
     */
    public function tagBreakdown(int $userId, CarbonImmutable $month): Collection
    {
        $current = $this->tagTotals($userId, $month);
        $previous = $this->tagTotals($userId, $month->subMonth())->keyBy('name');
        $palette = Category::DEFAULT_COLORS;

        return $current->values()->map(function (object $row, int $index) use ($previous, $palette): object {
            $row->color = $palette[$index % count($palette)];
            $row->previous = $previous->has($row->name)
                ? $previous->get($row->name)->total
                : null;

            return $row;
        });
    }

    /**
     * Categories with the largest absolute spending change versus the previous month.
     *
     * @return array<int, array{name: string, color: ?string, change: float, percent: ?float}>
     */
    public function topMovers(int $userId, CarbonImmutable $month, int $limit = 5): array
    {
        $current = $this->categoryTotals($userId, $month)->keyBy('name');
        $previous = $this->categoryTotals($userId, $month->subMonth())->keyBy('name');

        return $current->keys()
            ->merge($previous->keys())
            ->unique()
            ->map(function (string $name) use ($current, $previous): array {
                $now = $current->get($name)->total ?? 0.0;
                $before = $previous->get($name)->total ?? 0.0;

                return [
                    'name' => $name,
                    'color' => $current->get($name)->color ?? $previous->get($name)->color ?? null,
                    'change' => round($now - $before, 2),
                    'percent' => $this->percentChange($now, $before),
                ];
            })
            ->filter(fn (array $mover): bool => $mover['change'] != 0.0)
            ->sortByDesc(fn (array $mover): float => abs($mover['change']))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Income totals per category for the month, including uncategorized income.
     *
     * @return Collection<int, object>
     */
    public function incomeSources(int $userId, CarbonImmutable $month): Collection
    {
        $current = $this->categoryTotals($userId, $month, TransactionType::Income);
        $previous = $this->categoryTotals($userId, $month->subMonth(), TransactionType::Income)->keyBy('name');

        return $current->map(function (object $row) use ($previous): object {
            $row->previous = $previous->has($row->name)
                ? $previous->get($row->name)->total
                : null;

            return $row;
        })->values();
    }

    /**
     * Monthly income, expense and net series ending at the given month, plus the
     * average expense across completed months (the partial current month and any
     * future months are excluded from the average).
     *
     * @return array{
     *     labels: array<int, string>,
     *     income: array<int, float>,
     *     expense: array<int, float>,
     *     net: array<int, float>,
     *     average_expense: ?float
     * }
     */
    public function trends(int $userId, CarbonImmutable $month, int $months = 6): array
    {
        $labels = [];
        $income = [];
        $expense = [];
        $net = [];
        $completedExpenses = [];
        $currentMonth = CarbonImmutable::now()->startOfMonth();

        for ($i = $months - 1; $i >= 0; $i--) {
            $target = $month->subMonths($i);
            $totals = $this->monthTotals($userId, $target);

            $labels[] = $target->format('M Y');
            $income[] = $totals['income'];
            $expense[] = $totals['expense'];
            $net[] = round($totals['income'] - $totals['expense'], 2);

            if ($target->startOfMonth()->lt($currentMonth)) {
                $completedExpenses[] = $totals['expense'];
            }
        }

        return [
            'labels' => $labels,
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'average_expense' => $completedExpenses === []
                ? null
                : round(array_sum($completedExpenses) / count($completedExpenses), 2),
        ];
    }

    /**
     * Income and expense category lines for a P&L statement covering the given
     * month and the ($months - 1) preceding ones, oldest column first. Categories
     * active in any covered month appear, so a category that dropped to zero
     * still shows its decline.
     *
     * @return array{
     *     income: array<int, array{id: ?int, name: string, color: ?string, values: array<int, float>}>,
     *     expense: array<int, array{id: ?int, name: string, color: ?string, values: array<int, float>}>
     * }
     */
    public function statement(int $userId, CarbonImmutable $month, int $months = 2): array
    {
        $build = function (TransactionType $type) use ($userId, $month, $months): array {
            $monthly = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $monthly[] = $this->categoryTotals($userId, $month->subMonths($i), $type)->keyBy('name');
            }

            return collect($monthly)
                ->flatMap(fn (Collection $totals) => $totals->keys())
                ->unique()
                ->map(function (string $name) use ($monthly): array {
                    $latest = collect($monthly)->reverse()->first(fn (Collection $totals) => $totals->has($name))->get($name);

                    return [
                        'id' => $latest->id,
                        'name' => $name,
                        'color' => $latest->color,
                        'values' => array_map(
                            fn (Collection $totals): float => (float) ($totals->get($name)->total ?? 0.0),
                            $monthly
                        ),
                    ];
                })
                ->sortByDesc(fn (array $row): float => end($row['values']))
                ->values()
                ->all();
        };

        return [
            'income' => $build(TransactionType::Income),
            'expense' => $build(TransactionType::Expense),
        ];
    }

    /**
     * Monthly cashflow: net income from the P&L, minus debt service (transfers into
     * the user's own loan accounts), giving truly free cash. Credit card payments are
     * not debt service here - card spending is already expensed when charged, so the
     * bill payment would double count. Loans flagged exclude_from_net_worth (held for
     * someone else) are skipped.
     *
     * @return array{
     *     labels: array<int, string>,
     *     income: array<int, float>,
     *     expense: array<int, float>,
     *     net: array<int, float>,
     *     debt_service: array<int, array{name: string, values: array<int, float>}>,
     *     debt_total: array<int, float>,
     *     free: array<int, float>
     * }
     */
    public function cashflow(int $userId, CarbonImmutable $month, int $months = 4): array
    {
        $labels = [];
        $income = [];
        $expense = [];
        $net = [];
        $monthList = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $target = $month->subMonths($i);
            $monthList[] = $target;
            $labels[] = $target->format('M Y');

            $totals = $this->monthTotals($userId, $target);
            $income[] = $totals['income'];
            $expense[] = $totals['expense'];
            $net[] = round($totals['income'] - $totals['expense'], 2);
        }

        $debtService = Account::query()
            ->where('user_id', $userId)
            ->where('type', AccountType::Loan)
            ->where('exclude_from_net_worth', false)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Account $loan): array => [
                'name' => $loan->name,
                'values' => array_map(
                    fn (CarbonImmutable $target): float => round(Transaction::query()
                        ->where('user_id', $userId)
                        ->where('type', TransactionType::Transfer)
                        ->where('to_account_id', $loan->id)
                        ->whereBetween('date', [$target->startOfMonth(), $target->endOfMonth()])
                        ->sum('amount') / 100, 2),
                    $monthList
                ),
            ])
            ->filter(fn (array $row): float => array_sum($row['values']) > 0)
            ->values()
            ->all();

        $debtTotal = array_map(
            fn (int $index): float => round(array_sum(array_map(
                fn (array $row): float => $row['values'][$index],
                $debtService
            )), 2),
            range(0, $months - 1)
        );

        return [
            'labels' => $labels,
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'debt_service' => $debtService,
            'debt_total' => $debtTotal,
            'free' => array_map(
                fn (int $index): float => round($net[$index] - $debtTotal[$index], 2),
                range(0, $months - 1)
            ),
        ];
    }

    /**
     * Reconcile liquid cash (bank + cash accounts, excluding accounts held for someone
     * else) month by month: opening balance, the flows that moved it, closing balance.
     *
     * Flow buckets partition every cash movement, so opening + flows always equals
     * closing, and the latest closing equals the live sum of liquid balances:
     * - income:        income received into cash accounts
     * - expenses:      expenses paid from cash accounts (card charges are NOT cash)
     * - card_payments: cash sent to settle own credit cards
     * - loan_payments: cash sent into own loan accounts
     * - relay:         net cash exchanged with held-for-someone-else accounts
     * - other:         net of everything else (investment purchases, card cash advances)
     *
     * @return array{
     *     labels: array<int, string>,
     *     opening: array<int, float>,
     *     income: array<int, float>,
     *     expenses: array<int, float>,
     *     card_payments: array<int, float>,
     *     loan_payments: array<int, float>,
     *     relay: array<int, float>,
     *     other: array<int, float>,
     *     closing: array<int, float>
     * }
     */
    public function cashReconciliation(int $userId, CarbonImmutable $month, int $months = 4): array
    {
        $accounts = Account::query()
            ->where('user_id', $userId)
            ->get(['id', 'type', 'initial_balance', 'exclude_from_net_worth']);

        $liquid = $accounts
            ->filter(fn (Account $account): bool => in_array($account->type, [AccountType::Bank, AccountType::Cash], true)
                && ! $account->exclude_from_net_worth)
            ->keyBy('id');

        $excluded = $accounts->filter(fn (Account $account): bool => (bool) $account->exclude_from_net_worth)->keyBy('id');
        $types = $accounts->keyBy('id')->map(fn (Account $account): AccountType => $account->type);

        $running = (int) $liquid->sum(fn (Account $account): int => (int) $account->getRawOriginal('initial_balance'));

        $windowStart = $month->subMonths($months - 1)->startOfMonth();
        $buckets = [];
        $labels = [];
        $opening = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $labels[] = $month->subMonths($i)->format('M Y');
            $buckets[] = ['income' => 0, 'expenses' => 0, 'card_payments' => 0, 'loan_payments' => 0, 'relay' => 0, 'other' => 0];
        }

        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->orderBy('date')
            ->get(['type', 'date', 'amount', 'account_id', 'from_account_id', 'to_account_id']);

        $openingCaptured = false;

        foreach ($transactions as $transaction) {
            if (! $openingCaptured && $transaction->date->gte($windowStart)) {
                $opening[0] = $running;
                $openingCaptured = true;
            }

            $index = $openingCaptured
                ? min($months - 1, max(0, (int) $windowStart->diffInMonths($transaction->date->startOfMonth())))
                : null;

            $amount = (int) $transaction->getRawOriginal('amount');

            if ($transaction->type === TransactionType::Transfer) {
                $fromLiquid = $liquid->has($transaction->from_account_id);
                $toLiquid = $liquid->has($transaction->to_account_id);

                if ($fromLiquid === $toLiquid) {
                    continue;
                }

                $running += $toLiquid ? $amount : -$amount;

                if ($index !== null && $transaction->date->lte($month->endOfMonth())) {
                    $counterparty = $toLiquid ? $transaction->from_account_id : $transaction->to_account_id;
                    $signed = $toLiquid ? $amount : -$amount;

                    $bucket = match (true) {
                        $excluded->has($counterparty) => 'relay',
                        ! $toLiquid && ($types[$counterparty] ?? null) === AccountType::CreditCard => 'card_payments',
                        ! $toLiquid && ($types[$counterparty] ?? null) === AccountType::Loan => 'loan_payments',
                        default => 'other',
                    };

                    $buckets[$index][$bucket] += $signed;
                }

                continue;
            }

            if (! $liquid->has($transaction->account_id)) {
                continue;
            }

            $signed = $transaction->type === TransactionType::Income ? $amount : -$amount;
            $running += $signed;

            if ($index !== null && $transaction->date->lte($month->endOfMonth())) {
                $buckets[$index][$transaction->type === TransactionType::Income ? 'income' : 'expenses'] += $signed;
            }
        }

        if (! $openingCaptured) {
            $opening[0] = $running;
        }

        $result = [
            'labels' => $labels,
            'opening' => [],
            'income' => [],
            'expenses' => [],
            'card_payments' => [],
            'loan_payments' => [],
            'relay' => [],
            'other' => [],
            'closing' => [],
        ];

        $carry = $opening[0];

        foreach ($buckets as $bucket) {
            $result['opening'][] = round($carry / 100, 2);

            foreach (['income', 'expenses', 'card_payments', 'loan_payments', 'relay', 'other'] as $key) {
                $result[$key][] = round($bucket[$key] / 100, 2);
            }

            $carry += array_sum($bucket);
            $result['closing'][] = round($carry / 100, 2);
        }

        return $result;
    }

    public function hasTaggedTransactions(int $userId): bool
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereHas('tags', fn ($query) => $query->where('type', "user_{$userId}"))
            ->exists();
    }

    /**
     * @return array{income: float, expense: float}
     */
    protected function monthTotals(int $userId, CarbonImmutable $month): array
    {
        $totals = Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('type', [TransactionType::Income, TransactionType::Expense])
            ->whereBetween('date', [$month->startOfMonth(), $month->endOfMonth()])
            ->groupBy('type')
            ->select('type', DB::raw('SUM(amount) as total'))
            ->toBase()
            ->pluck('total', 'type');

        return [
            'income' => round(($totals[TransactionType::Income->value] ?? 0) / 100, 2),
            'expense' => round(($totals[TransactionType::Expense->value] ?? 0) / 100, 2),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    protected function categoryTotals(
        int $userId,
        CarbonImmutable $month,
        TransactionType $type = TransactionType::Expense
    ): Collection {
        return Transaction::query()
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', $type)
            ->whereBetween('transactions.date', [$month->startOfMonth(), $month->endOfMonth()])
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                DB::raw("COALESCE(categories.name, 'Uncategorized') as name"),
                'categories.color',
                DB::raw('COUNT(transactions.id) as count'),
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(function (object $row): object {
                $row->total = round($row->total / 100, 2);

                return $row;
            });
    }

    /**
     * @return Collection<int, object>
     */
    protected function tagTotals(int $userId, CarbonImmutable $month): Collection
    {
        return Transaction::query()
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Expense)
            ->whereBetween('transactions.date', [$month->startOfMonth(), $month->endOfMonth()])
            ->join('taggables', function ($join) {
                $join->on('transactions.id', '=', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', Transaction::class);
            })
            ->join('tags', 'taggables.tag_id', '=', 'tags.id')
            ->where('tags.type', "user_{$userId}")
            ->select(
                'tags.id',
                'tags.name',
                DB::raw('COUNT(DISTINCT transactions.id) as count'),
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('tags.id', 'tags.name')
            ->orderByDesc('total')
            ->get()
            ->map(function (object $row): object {
                $decoded = json_decode((string) $row->name, true);
                $row->name = is_array($decoded) ? (string) reset($decoded) : (string) $row->name;
                $row->total = round($row->total / 100, 2);

                return $row;
            });
    }

    protected function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
