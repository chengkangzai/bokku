<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;

class NetWorthHistory extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Net Worth History';

    protected static ?string $title = 'Net Worth History';

    protected static string|\UnitEnum|null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.net-worth-history';

    public int $months = 12;

    public function setMonths(int $months): void
    {
        if (! in_array($months, [6, 12, 24], true)) {
            return;
        }

        $this->months = $months;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $history = $user->netWorthBreakdownHistory($this->months);

        $rows = [];
        $previous = null;

        foreach ($history as $month => $point) {
            $value = $point['net'];

            $rows[] = [
                'month' => $month,
                'label' => CarbonImmutable::createFromFormat('Y-m', $month)->format('M Y'),
                'value' => $value,
                'assets' => $point['assets'],
                'liabilities' => $point['liabilities'],
                'change' => $previous === null ? null : round($value - $previous, 2),
                'percent' => ($previous === null || $previous == 0.0)
                    ? null
                    : round(($value - $previous) / abs($previous) * 100, 1),
            ];

            $previous = $value;
        }

        return [
            'rows' => $rows,
            'netWorth' => $user->net_worth,
            'totalAssets' => $user->total_assets,
            'totalLiabilities' => $user->total_liabilities,
            'chart' => [
                'labels' => array_column($rows, 'label'),
                'values' => array_column($rows, 'value'),
                'assets' => array_column($rows, 'assets'),
                'liabilities' => array_column($rows, 'liabilities'),
            ],
        ];
    }
}
