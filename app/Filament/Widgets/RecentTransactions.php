<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
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

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('description')
                    ->limit(30),

                TextColumn::make('amount')
                    ->money('myr')
                    ->color(fn (Transaction $record) => $record->type->getColor()),

                TextColumn::make('account.name')
                    ->label('Account'),

                TextColumn::make('category.name')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ])
            ->recordUrl(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record]))
            ->paginated(false);
    }
}
