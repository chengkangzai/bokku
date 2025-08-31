<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\Action;
use App\Models\Account;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AccountBalances extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Account Balances';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Account::query()
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
            )
            ->columns([
                TextColumn::make('name'),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'bank',
                        'success' => 'cash',
                        'warning' => 'credit_card',
                        'danger' => 'loan',
                    ])
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state)),

                TextColumn::make('balance')
                    ->label('Balance/Outstanding')
                    ->formatStateUsing(fn (Account $record) => $record->formatted_balance)
                    ->color(fn (Account $record) => $record->type === 'loan' ? 'danger' : ($record->balance >= 0 ? 'success' : 'danger')),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Account $record): string => route('filament.admin.resources.accounts.edit', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
