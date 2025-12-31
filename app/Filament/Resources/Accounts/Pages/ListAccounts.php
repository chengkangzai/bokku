<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\AccountType;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $userId = auth()->id();

        return [
            'all' => Tab::make('All')
                ->badge(Account::where('user_id', $userId)->count()),
            'assets' => Tab::make('Assets')
                ->icon('heroicon-o-arrow-trending-up')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', [AccountType::Bank, AccountType::Cash]))
                ->badge(Account::where('user_id', $userId)->whereIn('type', [AccountType::Bank, AccountType::Cash])->count()),
            'liabilities' => Tab::make('Liabilities')
                ->icon('heroicon-o-arrow-trending-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', [AccountType::CreditCard, AccountType::Loan]))
                ->badge(Account::where('user_id', $userId)->whereIn('type', [AccountType::CreditCard, AccountType::Loan])->count()),
        ];
    }
}
