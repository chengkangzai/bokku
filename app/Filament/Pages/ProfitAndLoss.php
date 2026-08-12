<?php

namespace App\Filament\Pages;

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
        $statement = app(SpendingAnalysisService::class)->statement(auth()->id(), $month);

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
            $totals[$section] = [
                'current' => round(array_sum(array_column($statement[$section], 'current')), 2),
                'previous' => round(array_sum(array_column($statement[$section], 'previous')), 2),
            ];
        }

        $net = [
            'current' => round($totals['income']['current'] - $totals['expense']['current'], 2),
            'previous' => round($totals['income']['previous'] - $totals['expense']['previous'], 2),
        ];

        return [
            'statement' => $statement,
            'totals' => $totals,
            'net' => $net,
            'savingsRate' => $totals['income']['current'] > 0
                ? round($net['current'] / $totals['income']['current'] * 100, 1)
                : null,
            'monthLabel' => $month->format('M Y'),
            'previousLabel' => $month->subMonth()->format('M Y'),
        ];
    }
}
