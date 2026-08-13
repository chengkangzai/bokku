<?php

namespace App\Filament\Resources\Accounts\Widgets;

use App\Enums\AccountType;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetWorthOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $history = $user->netWorthHistory();
        $netWorth = $user->net_worth;

        $assetCount = $user->accounts()
            ->whereIn('type', AccountType::assetTypes())
            ->count();

        $liabilityCount = $user->accounts()
            ->whereIn('type', [AccountType::CreditCard, AccountType::Loan])
            ->count();

        return [
            Stat::make('Net Worth', $this->formatMoney($netWorth))
                ->description($this->describeChange($history))
                ->descriptionIcon($this->changeAmount($history) >= 0
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->chart(array_values($history))
                ->color($netWorth >= 0 ? 'success' : 'danger'),

            Stat::make('Total Assets', $this->formatMoney($user->total_assets))
                ->description($this->describeAccountCount($assetCount))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Liabilities', $this->formatMoney($user->total_liabilities))
                ->description($this->describeAccountCount($liabilityCount))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('danger'),
        ];
    }

    protected function formatMoney(float $amount): string
    {
        return 'MYR '.number_format($amount, 2);
    }

    /**
     * @param  array<string, float>  $history
     */
    protected function changeAmount(array $history): float
    {
        $values = array_values($history);

        if (count($values) < 2) {
            return 0.0;
        }

        return $values[count($values) - 1] - $values[count($values) - 2];
    }

    /**
     * @param  array<string, float>  $history
     */
    protected function describeChange(array $history): string
    {
        $values = array_values($history);

        if (count($values) < 2) {
            return 'No prior data';
        }

        $change = $this->changeAmount($history);
        $previous = $values[count($values) - 2];

        if ($previous == 0.0) {
            return $this->formatMoney($change).' vs last month';
        }

        $percentage = number_format(abs($change / $previous) * 100, 1);

        return $this->formatMoney($change)." ({$percentage}%) vs last month";
    }

    protected function describeAccountCount(int $count): string
    {
        return $count.' '.str('account')->plural($count);
    }
}
