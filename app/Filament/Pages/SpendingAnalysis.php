<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountBalancesWidget;
use App\Filament\Widgets\IncomeSourcesWidget;
use App\Filament\Widgets\SmartInsightsWidget;
use App\Filament\Widgets\SpendingByCategoryChart;
use App\Filament\Widgets\SpendingByCategoryTable;
use App\Filament\Widgets\SpendingByTagsChart;
use App\Filament\Widgets\SpendingByTagsTable;
use App\Filament\Widgets\SpendingStatsOverview;
use App\Filament\Widgets\SpendingTrendsChart;
use App\Filament\Widgets\TopExpensesWidget;
use Filament\Pages\Page;
use Spatie\Tags\Tag;

class SpendingAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Spending Analysis';

    protected static ?string $title = 'Spending Analysis';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.spending-analysis';

    protected function getHeaderWidgets(): array
    {
        $widgets = [
            SpendingStatsOverview::class,
            AccountBalancesWidget::class,
            TopExpensesWidget::class,
            IncomeSourcesWidget::class,
            SmartInsightsWidget::class,
            SpendingByCategoryChart::class,
            SpendingByCategoryTable::class,
        ];

        if ($this->userHasTags()) {
            $widgets[] = SpendingByTagsChart::class;
            $widgets[] = SpendingByTagsTable::class;
        }

        $widgets[] = SpendingTrendsChart::class;

        return $widgets;
    }

    protected function userHasTags(): bool
    {
        return Tag::query()
            ->where('type', 'user_'.auth()->id())
            ->exists();
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
