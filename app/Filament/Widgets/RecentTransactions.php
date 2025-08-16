<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\Action;
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
                TextColumn::make('date')
                    ->date()
                    ->sortable(),

                BadgeColumn::make('type')
                    ->colors([
                        'success' => 'income',
                        'danger' => 'expense',
                        'primary' => 'transfer',
                    ]),

                TextColumn::make('description')
                    ->limit(30),

                TextColumn::make('amount')
                    ->money('myr')
                    ->color(fn (Transaction $record) => match ($record->type) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'primary',
                    }),

                TextColumn::make('account.name')
                    ->label('Account'),

                TextColumn::make('category.name')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false);
    }
}
