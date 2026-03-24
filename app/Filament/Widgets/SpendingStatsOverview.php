<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SpendingStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $userId = auth()->id();
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $totalBalance = Account::where('user_id', $userId)->sum('balance') / 100;

        $monthlyIncome = Transaction::where('user_id', $userId)
            ->where('type', TransactionType::Income)
            ->whereBetween('date', [$start, $end])
            ->sum('amount') / 100;

        $monthlyExpenses = Transaction::where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->whereBetween('date', [$start, $end])
            ->sum('amount') / 100;

        $savingsRate = $monthlyIncome > 0
            ? round((($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100, 1)
            : 0.0;

        return [
            Stat::make('Total Balance', 'MYR '.number_format($totalBalance, 2))
                ->description('All Accounts')
                ->descriptionIcon($totalBalance >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($totalBalance >= 0 ? 'success' : 'danger'),

            Stat::make('Monthly Income', 'MYR '.number_format($monthlyIncome, 2))
                ->description('This Month')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Monthly Expenses', 'MYR '.number_format($monthlyExpenses, 2))
                ->description('This Month')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make('Savings Rate', "{$savingsRate}%")
                ->description('Income vs Expenses')
                ->descriptionIcon($savingsRate >= 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
                ->color($savingsRate >= 20 ? 'success' : ($savingsRate >= 0 ? 'warning' : 'danger')),
        ];
    }
}
