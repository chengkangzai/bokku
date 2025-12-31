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
            'bank' => Tab::make('Bank')
                ->icon('heroicon-o-building-library')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', AccountType::Bank))
                ->badge(Account::where('user_id', $userId)->where('type', AccountType::Bank)->count()),
            'cash' => Tab::make('Cash')
                ->icon('heroicon-o-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', AccountType::Cash))
                ->badge(Account::where('user_id', $userId)->where('type', AccountType::Cash)->count()),
            'credit_card' => Tab::make('Credit Card')
                ->icon('heroicon-o-credit-card')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', AccountType::CreditCard))
                ->badge(Account::where('user_id', $userId)->where('type', AccountType::CreditCard)->count()),
            'loan' => Tab::make('Loan')
                ->icon('heroicon-o-document-text')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', AccountType::Loan))
                ->badge(Account::where('user_id', $userId)->where('type', AccountType::Loan)->count()),
        ];
    }
}
