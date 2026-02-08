<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Facades\DB;

class SpendingByTagsChart extends ChartWidget
{
    use HasFiltersSchema;

    protected static ?int $sort = 8;

    protected ?string $heading = 'Spending by Tags';

    protected int|string|array $columnSpan = 1;

    protected bool $hasDeferredFilters = true;

    public static function canView(): bool
    {
        return Transaction::query()
            ->where('user_id', auth()->id())
            ->whereHas('tags', function ($query) {
                $query->where('type', 'user_'.auth()->id());
            })
            ->exists();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('start_date')
                ->label('Start Date')
                ->default(now()->startOfMonth()),
            DatePicker::make('end_date')
                ->label('End Date')
                ->default(now()->endOfMonth()),
        ]);
    }

    protected function getData(): array
    {
        $startDate = $this->filters['start_date'] ?? now()->startOfMonth();
        $endDate = $this->filters['end_date'] ?? now()->endOfMonth();

        $data = Transaction::query()
            ->where('transactions.user_id', auth()->id())
            ->where('transactions.type', TransactionType::Expense)
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->whereHas('tags', function ($query) {
                $query->where('type', 'user_'.auth()->id());
            })
            ->join('taggables', function ($join) {
                $join->on('transactions.id', '=', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', Transaction::class);
            })
            ->join('tags', 'taggables.tag_id', '=', 'tags.id')
            ->select(
                'tags.name',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('tags.id', 'tags.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        if ($data->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $colors = $this->generateColors($data->count());

        return [
            'labels' => $data->pluck('name')->toArray(),
            'datasets' => [
                [
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => $colors,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
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
                            const value = context.parsed || 0;
                            const amountInDollars = value / 100;
                            return context.label + ": MYR " + amountInDollars.toFixed(2);
                        }',
                    ],
                ],
            ],
            'responsive' => true,
            'maintainAspectRatio' => true,
        ];
    }

    protected function generateColors(int $count): array
    {
        $baseColors = [
            '#3b82f6',
            '#ef4444',
            '#10b981',
            '#f59e0b',
            '#8b5cf6',
            '#ec4899',
            '#06b6d4',
            '#84cc16',
            '#f97316',
            '#6366f1',
        ];

        return array_slice($baseColors, 0, $count);
    }
}
