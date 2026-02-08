<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class SpendingTrendsChart extends ChartWidget
{
    use HasFiltersSchema;

    protected static ?int $sort = 10;

    protected ?string $heading = 'Spending Trends';

    protected int|string|array $columnSpan = 'full';

    protected bool $hasDeferredFilters = true;

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('months')
                ->label('Time Period')
                ->options([
                    3 => '3 Months',
                    6 => '6 Months',
                    12 => '12 Months',
                ])
                ->default(6),
        ]);
    }

    protected function getData(): array
    {
        $months = $this->filters['months'] ?? 6;

        $labels = [];
        $incomeData = [];
        $expenseData = [];
        $netData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $income = Transaction::query()
                ->where('user_id', auth()->id())
                ->where('type', TransactionType::Income)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('amount');

            $expense = Transaction::query()
                ->where('user_id', auth()->id())
                ->where('type', TransactionType::Expense)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month)
                ->sum('amount');

            $incomeData[] = $income;
            $expenseData[] = $expense;
            $netData[] = $income - $expense;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $incomeData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Expenses',
                    'data' => $expenseData,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => 'Net',
                    'data' => $netData,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'enabled' => true,
                    'callbacks' => [
                        'label' => 'function(context) {
                            const value = context.parsed.y || 0;
                            const amountInDollars = value / 100;
                            return context.dataset.label + ": MYR " + amountInDollars.toFixed(2);
                        }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) {
                            return "MYR " + (value / 100).toFixed(0);
                        }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => true,
        ];
    }
}
