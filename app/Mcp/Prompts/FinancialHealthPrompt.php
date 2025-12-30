<?php

namespace App\Mcp\Prompts;

use App\Models\Account;
use App\Models\Transaction;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Prompt;

class FinancialHealthPrompt extends Prompt
{
    protected string $description = 'Get a comprehensive financial health assessment based on all accounts and transactions.';

    /**
     * @return Response|ResponseFactory|array<int, Response>
     */
    public function handle(Request $request): Response|ResponseFactory|array
    {
        $userId = $request->user()->id;

        $accounts = Account::where('user_id', $userId)->get();

        $totalBalance = $accounts->sum('balance');

        $accountsByType = $accounts->groupBy('type')->map(fn ($group) => [
            'count' => $group->count(),
            'total' => $group->sum('balance'),
        ]);

        $accountSummary = $accountsByType->map(fn ($data, $type) => "- {$type}: {$data['count']} account(s), \${$data['total']}")->implode("\n");

        $threeMonthIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->where('date', '>=', now()->subMonths(3))
            ->sum('amount');

        $threeMonthExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->where('date', '>=', now()->subMonths(3))
            ->sum('amount');

        $avgMonthlyIncome = $threeMonthIncome / 3;
        $avgMonthlyExpenses = $threeMonthExpenses / 3;
        $avgMonthlySavings = $avgMonthlyIncome - $avgMonthlyExpenses;

        $savingsRate = $avgMonthlyIncome > 0
            ? round(($avgMonthlySavings / $avgMonthlyIncome) * 100, 1)
            : 0;

        $emergencyFundMonths = $avgMonthlyExpenses > 0
            ? round($totalBalance / $avgMonthlyExpenses, 1)
            : 0;

        $lastMonthTransactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->count();

        $thisMonthTransactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        $reconciledCount = Transaction::where('user_id', $userId)
            ->where('is_reconciled', true)
            ->count();

        $totalTransactions = Transaction::where('user_id', $userId)->count();

        $reconciledRate = $totalTransactions > 0
            ? round(($reconciledCount / $totalTransactions) * 100, 1)
            : 0;

        $systemPrompt = <<<'MARKDOWN'
            You are a certified financial planner providing a comprehensive financial health assessment.
            Evaluate the user's financial situation holistically and provide specific, actionable recommendations.
            Consider emergency funds, savings rate, and spending patterns.
            Be encouraging but honest about areas that need improvement.
        MARKDOWN;

        $userPrompt = <<<MARKDOWN
            Please provide a comprehensive financial health assessment:

            **Account Overview:**
            - Total Balance Across All Accounts: \${$totalBalance}
            - Account Breakdown:
            {$accountSummary}

            **Cash Flow (3-Month Average):**
            - Average Monthly Income: \${$avgMonthlyIncome}
            - Average Monthly Expenses: \${$avgMonthlyExpenses}
            - Average Monthly Savings: \${$avgMonthlySavings}
            - Savings Rate: {$savingsRate}%

            **Financial Metrics:**
            - Emergency Fund Coverage: {$emergencyFundMonths} months of expenses
            - Expense Transactions (Last Month): {$lastMonthTransactions}
            - Expense Transactions (This Month): {$thisMonthTransactions}
            - Transaction Reconciliation Rate: {$reconciledRate}%

            Please provide:
            1. An overall financial health score (1-10) with explanation
            2. Top 3 strengths in my financial situation
            3. Top 3 areas that need improvement
            4. Specific action items I should take in the next 30 days
            5. Long-term recommendations for financial stability
        MARKDOWN;

        return [
            Response::text(trim($systemPrompt))->asAssistant(),
            Response::text(trim($userPrompt)),
        ];
    }
}
