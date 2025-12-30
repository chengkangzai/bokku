<?php

namespace App\Mcp\Resources;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class FinancialOverviewResource extends Resource
{
    protected string $uri = 'bokku://overview';

    protected string $mimeType = 'application/json';

    protected string $description = 'A comprehensive summary of the user\'s financial situation including accounts, recent activity, and key metrics.';

    public function handle(Request $request): Response
    {
        $userId = $request->user()->id;

        $accounts = Account::where('user_id', $userId)->get();
        $categories = Category::where('user_id', $userId)->get();

        $totalBalance = $accounts->sum('balance');
        $accountCount = $accounts->count();

        $thisMonthIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $thisMonthExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $lastMonthExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->sum('amount');

        $monthOverMonthChange = $lastMonthExpenses > 0
            ? round((($thisMonthExpenses - $lastMonthExpenses) / $lastMonthExpenses) * 100, 1)
            : 0;

        $unreconciledCount = Transaction::where('user_id', $userId)
            ->where('is_reconciled', false)
            ->count();

        $overview = [
            'generated_at' => now()->toIso8601String(),
            'accounts' => [
                'total_count' => $accountCount,
                'total_balance' => $totalBalance,
                'by_type' => $accounts->groupBy('type')->map(fn ($group) => [
                    'count' => $group->count(),
                    'balance' => $group->sum('balance'),
                ]),
            ],
            'categories' => [
                'total_count' => $categories->count(),
                'income_categories' => $categories->where('type', 'income')->count(),
                'expense_categories' => $categories->where('type', 'expense')->count(),
            ],
            'this_month' => [
                'income' => $thisMonthIncome,
                'expenses' => $thisMonthExpenses,
                'net' => $thisMonthIncome - $thisMonthExpenses,
                'expense_change_from_last_month' => "{$monthOverMonthChange}%",
            ],
            'action_items' => [
                'unreconciled_transactions' => $unreconciledCount,
            ],
        ];

        return Response::text(json_encode($overview, JSON_PRETTY_PRINT));
    }
}
