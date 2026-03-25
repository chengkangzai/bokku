<?php

namespace App\Filament\Widgets;

use App\Enums\AccountType;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use Filament\Actions\Action;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LiabilityAccounts extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Liabilities';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Account::query()
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->whereIn('type', [AccountType::Loan, AccountType::CreditCard])
                    ->orderByDesc('balance')
            )
            ->columns([
                TextColumn::make('name'),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('balance')
                    ->label('Outstanding')
                    ->formatStateUsing(fn (Account $record) => $record->formatted_balance)
                    ->color('danger')
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => 'MYR '.number_format($state / 100, 2))
                    ),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Account $record): string => AccountResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ])
            ->recordurl(fn (Account $record): string => AccountResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
