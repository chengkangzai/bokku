<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\SpendingAnalysisService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class SpendingAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Spending Analysis';

    protected static ?string $title = 'Spending Analysis';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.spending-analysis';

    public string $month = '';

    public int $trendMonths = 6;

    public string $groupBy = 'category';

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

    public function setTrendMonths(int $months): void
    {
        if (! in_array($months, [1, 3, 6, 12], true)) {
            return;
        }

        $this->trendMonths = $months;
    }

    public function setGroupBy(string $groupBy): void
    {
        if (! in_array($groupBy, ['category', 'tag'], true)) {
            return;
        }

        $this->groupBy = $groupBy;
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
        $service = app(SpendingAnalysisService::class);
        $userId = auth()->id();
        $month = $this->monthDate();

        $hasTags = $service->hasTaggedTransactions($userId);

        if (! $hasTags && $this->groupBy === 'tag') {
            $this->groupBy = 'category';
        }

        $breakdown = $this->groupBy === 'tag'
            ? $service->tagBreakdown($userId, $month)
            : $service->breakdown($userId, $month);

        return [
            'summary' => $service->summary($userId, $month),
            'breakdown' => $breakdown,
            'movers' => $service->topMovers($userId, $month),
            'incomeSources' => $service->incomeSources($userId, $month),
            'trends' => $service->trends($userId, $month, $this->trendMonths),
            'hasTags' => $hasTags,
            'monthLabel' => $month->format('M Y'),
            'drillUrl' => fn (?int $categoryId): ?string => $categoryId === null ? null : TransactionResource::getUrl().'?'.http_build_query([
                'filters' => [
                    'category_id' => ['value' => $categoryId],
                    'date' => [
                        'from' => $month->startOfMonth()->toDateString(),
                        'until' => $month->endOfMonth()->toDateString(),
                    ],
                ],
            ]),
        ];
    }
}
