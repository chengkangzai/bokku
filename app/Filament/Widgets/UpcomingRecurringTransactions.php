<?php

namespace App\Filament\Widgets;

use App\Models\RecurringTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingRecurringTransactions extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Upcoming Recurring Transactions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RecurringTransaction::query()
                    ->where('user_id', auth()->id())
                    ->active()
                    ->upcoming(14) // Next 14 days
                    ->orderBy('next_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'info',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'income' => 'heroicon-o-arrow-down-circle',
                        'expense' => 'heroicon-o-arrow-up-circle',
                        'transfer' => 'heroicon-o-arrow-right-circle',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => 'RM '.number_format($state, 2))
                    ->color(fn ($record) => match ($record->type) {
                        'income' => 'success',
                        'expense' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('next_date')
                    ->label('Due')
                    ->date()
                    ->description(fn ($record) => $record->next_date->diffForHumans())
                    ->color(fn ($record) => $record->isDue() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('frequency_label')
                    ->label('Frequency')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account'),
            ])
            ->actions([
                Tables\Actions\Action::make('process')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Transaction')
                    ->modalDescription('Create this transaction now?')
                    ->action(function (RecurringTransaction $record) {
                        $transaction = $record->generateTransaction();
                        if ($transaction) {
                            \Filament\Notifications\Notification::make()
                                ->title('Transaction Created')
                                ->success()
                                ->body("Transaction for {$record->description} has been created.")
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('skip')
                    ->label('Skip')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Skip Occurrence')
                    ->modalDescription('Skip this occurrence and move to the next date?')
                    ->action(function (RecurringTransaction $record) {
                        $record->skipOnce();
                        \Filament\Notifications\Notification::make()
                            ->title('Skipped')
                            ->success()
                            ->body("Next: {$record->next_date->format('M d, Y')}")
                            ->send();
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No upcoming recurring transactions')
            ->emptyStateDescription('Your recurring transactions will appear here when they are due')
            ->emptyStateIcon('heroicon-o-arrow-path')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label('Create Recurring Transaction')
                    ->url(route('filament.admin.resources.recurring-transactions.create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }
}
