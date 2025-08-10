<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'success' => 'income',
                        'danger' => 'expense',
                        'primary' => 'transfer',
                    ])
                    ->visible(fn () => $this->getOwnerRecord()->type === null),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('amount')
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

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_reconciled')
                    ->boolean()
                    ->label('✓'),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship(
                        'account',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                Tables\Filters\TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->placeholder('All transactions')
                    ->trueLabel('Reconciled only')
                    ->falseLabel('Unreconciled only'),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('date_to')
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
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', ['record' => $record])
                    ),
                Tables\Actions\EditAction::make()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.transactions.edit', ['record' => $record])
                    ),
            ])
            ->bulkActions([
                // Remove bulk actions for safety
            ])
            ->defaultSort('date', 'desc');
    }
}
