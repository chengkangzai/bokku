<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $accountId = $this->getOwnerRecord()->id;

        return Transaction::query()
            ->where('user_id', $this->getOwnerRecord()->user_id)
            ->where(function ($query) use ($accountId) {
                $query->where('account_id', $accountId)
                    ->orWhere('from_account_id', $accountId)
                    ->orWhere('to_account_id', $accountId);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('amount')
                    ->money('myr')
                    ->sortable()
                    ->color(fn (Transaction $record) => $record->type->getColor())
                    ->formatStateUsing(function (Transaction $record) {
                        $accountId = $this->getOwnerRecord()->id;
                        $amount = number_format($record->amount, 2);

                        if ($record->type === TransactionType::Transfer) {
                            // Show + for incoming transfers, - for outgoing
                            if ($record->to_account_id === $accountId) {
                                return '+RM '.$amount;
                            } else {
                                return '-RM '.$amount;
                            }
                        }

                        return match ($record->type) {
                            TransactionType::Income => '+RM '.$amount,
                            TransactionType::Expense => '-RM '.$amount,
                            default => 'RM '.$amount,
                        };
                    }),

                TextColumn::make('category.name')
                    ->placeholder('—')
                    ->sortable()
                    ->visible(fn () => true),

                TextColumn::make('transfer_info')
                    ->label('Transfer Details')
                    ->getStateUsing(function (Transaction $record) {
                        if ($record->type !== TransactionType::Transfer) {
                            return null;
                        }

                        $accountId = $this->getOwnerRecord()->id;

                        if ($record->from_account_id === $accountId) {
                            return '→ '.($record->toAccount?->name ?? 'Unknown');
                        } elseif ($record->to_account_id === $accountId) {
                            return '← '.($record->fromAccount?->name ?? 'Unknown');
                        }

                        return null;
                    })
                    ->placeholder('—')
                    ->visible(fn () => true),

                IconColumn::make('is_reconciled')
                    ->boolean()
                    ->label('✓'),

                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(TransactionType::class),

                TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->placeholder('All transactions')
                    ->trueLabel('Reconciled only')
                    ->falseLabel('Unreconciled only'),
            ])
            ->headerActions([
                // Remove create action as transactions should be created from the main transaction resource
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', ['record' => $record])
                    ),
                EditAction::make()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', ['record' => $record])
                    ),
            ])
            ->toolbarActions([
                // Remove bulk actions for safety
            ])
            ->defaultSort('date', 'desc');
    }
}
