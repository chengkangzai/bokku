<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\SpendingAnalysisService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class ProfitAndLoss extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'P&L Statement';

    protected static ?string $title = 'Profit & Loss';

    protected static string|\UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.profit-and-loss';

    public string $month = '';

    public bool $excludeCompany = false;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthDate()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        if (! $this->canGoToNextMonth()) {
            return;
        }

        $this->month = $this->monthDate()->addMonth()->format('Y-m');
    }

    public function canGoToNextMonth(): bool
    {
        return $this->monthDate()->startOfMonth()->lt(CarbonImmutable::now()->startOfMonth());
    }

    protected function monthDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $month = $this->monthDate();
        $columns = 3;
        $statement = app(SpendingAnalysisService::class)->statement(auth()->id(), $month, $columns);

        if ($this->excludeCompany) {
            foreach (['income', 'expense'] as $section) {
                $statement[$section] = array_values(array_filter(
                    $statement[$section],
                    fn (array $row): bool => $row['name'] !== 'Company Expenses'
                ));
            }
        }

        $totals = [];

        foreach (['income', 'expense'] as $section) {
            $totals[$section] = array_map(
                fn (int $index): float => round(array_sum(array_map(
                    fn (array $row): float => $row['values'][$index],
                    $statement[$section]
                )), 2),
                range(0, $columns - 1)
            );
        }

        $net = array_map(
            fn (int $index): float => round($totals['income'][$index] - $totals['expense'][$index], 2),
            range(0, $columns - 1)
        );

        return [
            'drillUrl' => function (?int $categoryId, string $type) use ($month): string {
                $filters = [
                    'type' => ['value' => $type],
                    'date' => [
                        'from' => $month->startOfMonth()->toDateString(),
                        'until' => $month->endOfMonth()->toDateString(),
                    ],
                ];

                if ($categoryId === null) {
                    $filters['uncategorized'] = ['isActive' => true];
                } else {
                    $filters['category_id'] = ['value' => $categoryId];
                }

                return TransactionResource::getUrl().'?'.http_build_query(['filters' => $filters]);
            },
            'statement' => $statement,
            'totals' => $totals,
            'net' => $net,
            'savingsRate' => end($totals['income']) > 0
                ? round(end($net) / end($totals['income']) * 100, 1)
                : null,
            'monthLabel' => $month->format('M Y'),
            'monthLabels' => array_map(
                fn (int $offset): string => $month->subMonths($offset)->format('M Y'),
                range($columns - 1, 0)
            ),
        ];
    }
}
