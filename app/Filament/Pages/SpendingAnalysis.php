<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SpendingByCategoryChart;
use App\Filament\Widgets\SpendingByCategoryTable;
use App\Filament\Widgets\SpendingByTagsChart;
use App\Filament\Widgets\SpendingByTagsTable;
use App\Filament\Widgets\SpendingTrendsChart;
use Filament\Pages\Page;

class SpendingAnalysis extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Spending Analysis';

    protected static ?string $title = 'Spending Analysis';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.spending-analysis';

    protected function getHeaderWidgets(): array
    {
        return [
            SpendingByCategoryChart::class,
            SpendingByCategoryTable::class,
            SpendingByTagsChart::class,
            SpendingByTagsTable::class,
            SpendingTrendsChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
