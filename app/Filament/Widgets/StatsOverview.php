<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $userId = auth()->id();

        $assets = Account::where('user_id', $userId)
            ->whereNotIn('type', ['loan', 'credit_card'])
            ->sum('balance') / 100;

        $liabilities = Account::where('user_id', $userId)
            ->whereIn('type', ['loan', 'credit_card'])
            ->sum('balance') / 100;

        $netWorth = $assets - $liabilities;

        $monthlyIncome = Transaction::where('user_id', $userId)
            ->where('type', TransactionType::Income)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount') / 100;

        $monthlyExpenses = Transaction::where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount') / 100;

        $monthlySavings = $monthlyIncome - $monthlyExpenses;

        return [
            Stat::make('Net Worth', 'MYR '.number_format($netWorth, 2))
                ->description($netWorth >= 0 ? 'Total assets' : 'Total liabilities')
                ->descriptionIcon($netWorth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netWorth >= 0 ? 'success' : 'danger'),

            Stat::make('Monthly Income', 'MYR '.number_format($monthlyIncome, 2))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Monthly Expenses', 'MYR '.number_format($monthlyExpenses, 2))
                ->description(now()->format('F Y'))
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make('Monthly Savings', 'MYR '.number_format($monthlySavings, 2))
                ->description($monthlySavings >= 0 ? 'Saved this month' : 'Overspent this month')
                ->descriptionIcon($monthlySavings >= 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
                ->color($monthlySavings >= 0 ? 'primary' : 'warning'),
        ];
    }
}
