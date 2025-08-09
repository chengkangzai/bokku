<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTransactions extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->where('user_id', auth()->id())
                    ->with(['account', 'category', 'toAccount'])
                    ->latest('date')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'income',
                        'danger' => 'expense',
                        'primary' => 'transfer',
                    ]),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
                    ->money('myr')
                    ->weight('bold')
                    ->color(fn (Transaction $record) => match ($record->type) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'primary',
                    }),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account'),

                Tables\Columns\TextColumn::make('category.name')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
