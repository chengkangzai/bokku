<?php

namespace App\Filament\Widgets;

use App\Models\Budget;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BudgetStats extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return Budget::where('user_id', auth()->id())
            ->where('is_active', true)
            ->exists();
    }

    protected function getStats(): array
    {
        $userId = auth()->id();

        $budgets = Budget::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $totalBudget = $budgets->sum('amount');
        $totalSpent = $budgets->sum(fn (Budget $budget) => $budget->getSpentAmount());
        $totalRemaining = $totalBudget - $totalSpent;

        $overBudgetCount = $budgets->filter(fn (Budget $budget) => $budget->isOverBudget())->count();
        $nearBudgetCount = $budgets->filter(fn (Budget $budget) => $budget->isNearBudget())->count();
        $onTrackCount = $budgets->count() - $overBudgetCount - $nearBudgetCount;

        return [
            Stat::make('Total Budget', 'MYR '.number_format($totalBudget, 2))
                ->description('Across all categories')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Total Spent', 'MYR '.number_format($totalSpent, 2))
                ->description($totalRemaining >= 0 ? 'MYR '.number_format($totalRemaining, 2).' remaining' : 'MYR '.number_format(abs($totalRemaining), 2).' over budget')
                ->descriptionIcon($totalRemaining >= 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($totalRemaining >= 0 ? 'success' : 'danger'),

            Stat::make('Budget Status', $onTrackCount.' on track')
                ->description($overBudgetCount > 0 ? $overBudgetCount.' over, '.$nearBudgetCount.' near limit' : 'All budgets healthy')
                ->descriptionIcon($overBudgetCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($overBudgetCount > 0 ? 'danger' : ($nearBudgetCount > 0 ? 'warning' : 'success')),
        ];
    }
}
