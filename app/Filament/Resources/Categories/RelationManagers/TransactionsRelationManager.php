<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Models\Transaction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'income',
                        'danger' => 'expense',
                        'primary' => 'transfer',
                    ])
                    ->visible(fn () => $this->getOwnerRecord()->type === null),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('amount')
                    ->money('myr')
                    ->sortable()
                    ->color(fn (Transaction $record) => match ($record->type) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'primary',
                    })
                    ->formatStateUsing(function (Transaction $record) {
                        $amount = number_format($record->amount, 2);

                        return match ($record->type) {
                            'income' => '+RM '.$amount,
                            'expense' => '-RM '.$amount,
                            'transfer' => 'RM '.$amount,
                            default => 'RM '.$amount,
                        };
                    }),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),

                IconColumn::make('is_reconciled')
                    ->boolean()
                    ->label('✓'),

                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship(
                        'account',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->placeholder('All transactions')
                    ->trueLabel('Reconciled only')
                    ->falseLabel('Unreconciled only'),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('From'),
                        DatePicker::make('date_to')
                            ->label('To'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
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
