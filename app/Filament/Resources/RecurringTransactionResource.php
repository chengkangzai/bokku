<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RecurringTransactionResource\Pages\ListRecurringTransactions;
use App\Filament\Resources\RecurringTransactionResource\Pages\CreateRecurringTransaction;
use App\Filament\Resources\RecurringTransactionResource\Pages\EditRecurringTransaction;
use App\Filament\Resources\RecurringTransactionResource\Pages;
use App\Models\RecurringTransaction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringTransactionResource extends Resource
{
    protected static ?string $model = RecurringTransaction::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string | \UnitEnum | null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        Select::make('type')
                            ->required()
                            ->options([
                                'income' => 'Income',
                                'expense' => 'Expense',
                                'transfer' => 'Transfer',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('category_id', null))
                            ->native(false),

                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->minValue(0.01),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(255),

                        Select::make('account_id')
                            ->label(fn (callable $get) => $get('type') === 'transfer' ? 'From Account' : 'Account')
                            ->relationship(
                                'account',
                                'name',
                                fn (Builder $query) => $query->where('user_id', auth()->id())
                            )
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->preload(),

                        Select::make('to_account_id')
                            ->label('To Account')
                            ->relationship(
                                'toAccount',
                                'name',
                                fn (Builder $query) => $query->where('user_id', auth()->id())
                            )
                            ->required(fn (callable $get) => $get('type') === 'transfer')
                            ->visible(fn (callable $get) => $get('type') === 'transfer')
                            ->native(false)
                            ->searchable()
                            ->preload(),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship(
                                'category',
                                'name',
                                fn (Builder $query, callable $get) => $query
                                    ->where('user_id', auth()->id())
                                    ->when(
                                        $get('type'),
                                        fn ($q, $type) => $q->where('type', $type === 'transfer' ? 'expense' : $type)
                                    )
                            )
                            ->required(fn (callable $get) => in_array($get('type'), ['income', 'expense']))
                            ->visible(fn (callable $get) => $get('type') !== 'transfer')
                            ->native(false)
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Recurrence Settings')
                    ->schema([
                        Select::make('frequency')
                            ->required()
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'annual' => 'Annual',
                            ])
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                // Reset specific day fields when frequency changes
                                $set('day_of_week', null);
                                $set('day_of_month', null);
                                $set('month_of_year', null);
                            })
                            ->native(false),

                        TextInput::make('interval')
                            ->label('Repeat Every')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(365)
                            ->suffix(fn (callable $get) => match ($get('frequency')) {
                                'daily' => 'day(s)',
                                'weekly' => 'week(s)',
                                'monthly' => 'month(s)',
                                'annual' => 'year(s)',
                                default => '',
                            }),

                        Select::make('day_of_week')
                            ->label('Day of Week')
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                            ])
                            ->visible(fn (callable $get) => $get('frequency') === 'weekly')
                            ->required(fn (callable $get) => $get('frequency') === 'weekly')
                            ->native(false),

                        TextInput::make('day_of_month')
                            ->label('Day of Month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->visible(fn (callable $get) => in_array($get('frequency'), ['monthly', 'annual']))
                            ->required(fn (callable $get) => $get('frequency') === 'monthly')
                            ->helperText('Enter 31 for last day of month'),

                        Select::make('month_of_year')
                            ->label('Month')
                            ->options([
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December',
                            ])
                            ->visible(fn (callable $get) => $get('frequency') === 'annual')
                            ->required(fn (callable $get) => $get('frequency') === 'annual')
                            ->native(false),
                    ])->columns(2),

                Section::make('Schedule')
                    ->description('Optional: Override automatic scheduling')
                    ->collapsed()
                    ->schema([
                        DatePicker::make('start_date')
                            ->default(now())
                            ->helperText('When this recurring transaction should begin'),

                        DatePicker::make('next_date')
                            ->label('Next Occurrence')
                            ->helperText('Leave empty to auto-calculate from start date')
                            ->placeholder('Auto-calculated if empty'),

                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->helperText('Leave empty for indefinite recurrence'),

                        DateTimePicker::make('last_processed')
                            ->label('Last Processed')
                            ->disabled()
                            ->helperText('Last time a transaction was generated'),
                    ])->columns(2),

                Section::make('Options')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable/disable this recurring transaction'),

                        Toggle::make('auto_process')
                            ->label('Auto Process')
                            ->default(true)
                            ->helperText('Automatically create transactions on schedule'),

                        Textarea::make('notes')
                            ->columnSpanFull()
                            ->rows(2),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
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

                TextColumn::make('description')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state) => 'RM '.number_format($state, 2))
                    ->sortable(),

                TextColumn::make('frequency_label')
                    ->label('Frequency')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_date')
                    ->label('Next Occurrence')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->isDue() ? 'danger' : 'gray'),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('auto_process')
                    ->label('Auto')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                        'transfer' => 'Transfer',
                    ]),

                SelectFilter::make('frequency')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'annual' => 'Annual',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                Filter::make('due')
                    ->query(fn (Builder $query): Builder => $query->due())
                    ->label('Due Now'),

                Filter::make('upcoming')
                    ->query(fn (Builder $query): Builder => $query->upcoming(7))
                    ->label('Next 7 Days'),
            ])
            ->recordActions([
                Action::make('process')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Transaction')
                    ->modalDescription('This will create a transaction for this recurring template and update the next occurrence date.')
                    ->action(function (RecurringTransaction $record) {
                        $transaction = $record->generateTransaction();
                        if ($transaction) {
                            Notification::make()
                                ->title('Transaction Created')
                                ->success()
                                ->body("Transaction for {$record->description} has been created.")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Transaction Not Due')
                                ->warning()
                                ->body('This recurring transaction is not due yet.')
                                ->send();
                        }
                    }),

                Action::make('skip')
                    ->label('Skip Once')
                    ->icon('heroicon-o-forward')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Skip This Occurrence')
                    ->modalDescription('This will skip the current occurrence and move to the next scheduled date.')
                    ->action(function (RecurringTransaction $record) {
                        $record->skipOnce();
                        Notification::make()
                            ->title('Occurrence Skipped')
                            ->success()
                            ->body("Next occurrence: {$record->next_date->format('M d, Y')}")
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('process_now')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Transactions')
                    ->modalDescription('This will create transactions for all selected recurring templates that are due.')
                    ->action(function ($records) {
                        $processed = 0;
                        $skipped = 0;

                        foreach ($records as $record) {
                            if ($record->is_active) {
                                $transaction = $record->generateTransaction();
                                if ($transaction) {
                                    $processed++;
                                } else {
                                    $skipped++;
                                }
                            } else {
                                $skipped++;
                            }
                        }

                        if ($processed > 0) {
                            Notification::make()
                                ->title('Transactions Created')
                                ->success()
                                ->body("Created {$processed} transaction(s). Skipped {$skipped}.")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No Transactions Created')
                                ->warning()
                                ->body('All selected recurring transactions were either not due or inactive.')
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkAction::make('toggle_active')
                    ->label('Toggle Active')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['is_active' => ! $record->is_active]);
                        }
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('next_date', 'asc')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringTransactions::route('/'),
            'create' => CreateRecurringTransaction::route('/create'),
            'edit' => EditRecurringTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $dueCount = static::getModel()::query()
            ->where('user_id', auth()->id())
            ->due()
            ->count();

        return $dueCount > 0 ? (string) $dueCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
