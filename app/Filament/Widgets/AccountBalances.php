<?php

namespace App\Filament\Widgets;

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
                Tables\Columns\TextColumn::make('name'),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'bank',
                        'success' => 'cash',
                        'warning' => 'credit_card',
                        'danger' => 'loan',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('balance')
                    ->money(fn (Account $record) => strtolower($record->currency))
                    ->color(fn (Account $record) => $record->balance >= 0 ? 'success' : 'danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Account $record): string => route('filament.admin.resources.accounts.edit', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
