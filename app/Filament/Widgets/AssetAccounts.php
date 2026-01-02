<?php

namespace App\Filament\Widgets;

use App\Enums\AccountType;
use App\Models\Account;
use Filament\Actions\Action;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AssetAccounts extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Assets';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Account::query()
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->whereIn('type', [AccountType::Bank, AccountType::Cash])
            )
            ->columns([
                TextColumn::make('name'),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->formatStateUsing(fn (Account $record) => $record->formatted_balance)
                    ->color('success')
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => 'MYR '.number_format($state / 100, 2))
                    ),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Account $record): string => route('filament.admin.resources.accounts.edit', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
