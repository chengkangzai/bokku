<?php

namespace App\Filament\Pages;

use App\Services\SpendingAnalysisService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class CashflowStatement extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Cashflow';

    protected static ?string $title = 'Cashflow';

    protected static string|\UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.cashflow-statement';

    public string $month = '';

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
        $cashflow = app(SpendingAnalysisService::class)->cashflow(auth()->id(), $month, 4);

        return [
            'cashflow' => $cashflow,
            'monthLabel' => $month->format('M Y'),
        ];
    }
}
