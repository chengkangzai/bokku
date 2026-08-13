<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
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
        $columns = 4;
        $cash = app(SpendingAnalysisService::class)->cashStatement(auth()->id(), $month, $columns);

        return [
            'cash' => $cash,
            'monthLabel' => $month->format('M Y'),
            'drillUrl' => function (?int $categoryId, string $type) use ($month, $columns): string {
                $filters = [
                    'type' => ['value' => $type],
                    'date' => [
                        'from' => $month->subMonths($columns - 1)->startOfMonth()->toDateString(),
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
        ];
    }
}
