<?php

namespace App\Filament\Pages;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Filament\Widgets\SpendingByCategoryChart;
use App\Filament\Widgets\SpendingByCategoryTable;
use App\Filament\Widgets\SpendingByTagsChart;
use App\Filament\Widgets\SpendingByTagsTable;
use App\Filament\Widgets\SpendingTrendsChart;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpendingAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Spending Analysis';

    protected static ?string $title = 'Spending Analysis';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.spending-analysis';

    protected function getHeaderWidgets(): array
    {
        return [
            SpendingByCategoryChart::class,
            SpendingByCategoryTable::class,
            SpendingByTagsChart::class,
            SpendingByTagsTable::class,
            SpendingTrendsChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getSummaryMetrics(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return [
            'total_balance' => $this->getTotalBalance(),
            'monthly_income' => $this->getMonthlyIncome($start, $end),
            'monthly_expenses' => $this->getMonthlyExpenses($start, $end),
            'savings_rate' => $this->getSavingsRate($start, $end),
        ];
    }

    protected function getTotalBalance(): int
    {
        return Account::query()
            ->where('user_id', auth()->id())
            ->sum('balance');
    }

    protected function getMonthlyIncome(Carbon $start, Carbon $end): int
    {
        return Transaction::query()
            ->where('user_id', auth()->id())
            ->where('type', TransactionType::Income)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    protected function getMonthlyExpenses(Carbon $start, Carbon $end): int
    {
        return Transaction::query()
            ->where('user_id', auth()->id())
            ->where('type', TransactionType::Expense)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
    }

    protected function getSavingsRate(Carbon $start, Carbon $end): float
    {
        $income = $this->getMonthlyIncome($start, $end);
        $expenses = $this->getMonthlyExpenses($start, $end);

        if ($income === 0) {
            return 0.0;
        }

        return round((($income - $expenses) / $income) * 100, 1);
    }

    public function getAccountsData(): Collection
    {
        return Account::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('balance')
            ->get()
            ->map(fn (Account $account) => [
                'name' => $account->name,
                'type' => $account->type->getLabel(),
                'balance' => $account->balance,
                'formatted_balance' => $account->formatted_balance,
                'is_positive' => $account->balance >= 0,
            ]);
    }

    public function getLoansData(): Collection
    {
        return Account::query()
            ->where('user_id', auth()->id())
            ->where('type', AccountType::Loan)
            ->get()
            ->map(function (Account $loan) {
                $balance = abs($loan->balance);

                return [
                    'name' => $loan->name,
                    'balance' => $balance,
                    'formatted_balance' => number_format($balance / 100, 2),
                ];
            });
    }

    public function getTopExpenseCategories(): Collection
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $data = Transaction::query()
            ->where('transactions.user_id', auth()->id())
            ->where('transactions.type', TransactionType::Expense)
            ->whereBetween('transactions.date', [$start, $end])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select('categories.name', 'categories.color', DB::raw('SUM(transactions.amount) as total'))
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $totalExpenses = $data->sum('total');

        return $data->map(function ($item) use ($totalExpenses) {
            $item->percentage = $totalExpenses > 0 ? round(($item->total / $totalExpenses) * 100, 1) : 0;
            $item->formatted_total = 'MYR '.number_format($item->total / 100, 2);

            return $item;
        });
    }

    public function getIncomeSourcesData(): Collection
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return Transaction::query()
            ->where('transactions.user_id', auth()->id())
            ->where('transactions.type', TransactionType::Income)
            ->whereBetween('transactions.date', [$start, $end])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(transactions.amount) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) {
                $item->formatted_total = 'MYR '.number_format($item->total / 100, 2);

                return $item;
            });
    }

    public function getSmartInsights(): array
    {
        $insights = [];

        // Budget warnings
        $budgetsAtRisk = Budget::query()
            ->where('user_id', auth()->id())
            ->get()
            ->filter(fn (Budget $budget) => $budget->getProgressPercentage() > 90);

        if ($budgetsAtRisk->isNotEmpty()) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
                'message' => "You're approaching or exceeding {$budgetsAtRisk->count()} budget limit(s) this month.",
            ];
        }

        // Savings achievement
        $savingsRate = $this->getSavingsRate(now()->startOfMonth(), now()->endOfMonth());
        if ($savingsRate > 20) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'heroicon-o-check-circle',
                'message' => "Great job! You're saving {$savingsRate}% of your income this month.",
            ];
        }

        // Top spending category
        $topCategory = $this->getTopExpenseCategories()->first();
        if ($topCategory) {
            $insights[] = [
                'type' => 'info',
                'icon' => 'heroicon-o-information-circle',
                'message' => "Your top expense this month is {$topCategory->name} ({$topCategory->formatted_total}).",
            ];
        }

        // Default insight if none
        if (empty($insights)) {
            $insights[] = [
                'type' => 'info',
                'icon' => 'heroicon-o-light-bulb',
                'message' => 'Track your spending to get personalized financial insights.',
            ];
        }

        return $insights;
    }
}
