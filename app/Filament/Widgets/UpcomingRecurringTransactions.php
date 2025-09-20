<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\RecurringTransaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('description')
                    ->weight('semibold'),

                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => 'MYR '.number_format($state, 2))
                    ->color(fn ($record) => $record->type->getColor()),

                TextColumn::make('next_date')
                    ->label('Due')
                    ->date()
                    ->description(fn ($record) => $record->next_date->diffForHumans())
                    ->color(fn ($record) => $record->isDue() ? 'danger' : 'gray'),

                TextColumn::make('frequency_label')
                    ->label('Frequency')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('account.name')
                    ->label('Account'),
            ])
            ->recordActions([
                Action::make('process')
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
                            Notification::make()
                                ->title('Transaction Created')
                                ->success()
                                ->body("Transaction for {$record->description} has been created.")
                                ->send();
                        }
                    }),

                Action::make('skip')
                    ->label('Skip')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading('Skip Occurrence')
                    ->modalDescription('Skip this occurrence and move to the next date?')
                    ->action(function (RecurringTransaction $record) {
                        $record->skipOnce();
                        Notification::make()
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
                Action::make('create')
                    ->label('Create Recurring Transaction')
                    ->url(route('filament.admin.resources.recurring-transactions.create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }
}
