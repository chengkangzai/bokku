<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\Transaction;
use Filament\Widgets\Widget;

class SmartInsightsWidget extends Widget
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.smart-insights-widget';

    protected int|string|array $columnSpan = 'full';

    public function getInsights(): array
    {
        $insights = [];
        $userId = auth()->id();
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $income = Transaction::where('user_id', $userId)
            ->where('type', TransactionType::Income)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $expenses = Transaction::where('user_id', $userId)
            ->where('type', TransactionType::Expense)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $savingsRate = $income > 0
            ? round((($income - $expenses) / $income) * 100, 1)
            : 0.0;

        $budgetsAtRisk = Budget::where('user_id', $userId)
            ->get()
            ->filter(fn (Budget $budget) => $budget->getProgressPercentage() > 90);

        if ($budgetsAtRisk->isNotEmpty()) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
                'message' => "You're approaching or exceeding {$budgetsAtRisk->count()} budget limit(s) this month.",
            ];
        }

        if ($savingsRate > 20) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'heroicon-o-check-circle',
                'message' => "Great job! You're saving {$savingsRate}% of your income this month.",
            ];
        }

        $topCategory = Transaction::query()
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Expense)
            ->whereBetween('transactions.date', [$start, $end])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, SUM(transactions.amount) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->first();

        if ($topCategory) {
            $formatted = 'MYR '.number_format($topCategory->total / 100, 2);

            $insights[] = [
                'type' => 'info',
                'icon' => 'heroicon-o-information-circle',
                'message' => "Your top expense this month is {$topCategory->name} ({$formatted}).",
            ];
        }

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
