<?php

namespace App\Filament\Resources\Accounts;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\RelationManagers\TransactionsRelationManager;
use App\Models\Account;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Main Checking Account'),

                        Select::make('type')
                            ->required()
                            ->options(AccountType::class)
                            ->native(false),

                        TextInput::make('initial_balance')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('MYR')
                            ->label(fn (Get $get) => match ($get('type')) {
                                AccountType::Loan => 'Total Amount Owed',
                                AccountType::CreditCard => 'Outstanding Balance',
                                default => 'Initial Balance'
                            })
                            ->helperText(fn (Get $get, Component $livewire) => $livewire instanceof EditAccount
                                    ? 'Initial balance cannot be changed after account creation. Use the "Adjust Balance" button to make adjustments.'
                                    : match ($get('type')) {
                                        AccountType::Loan => 'Enter as positive amount (e.g., 60000 for MYR 60,000 loan)',
                                        AccountType::CreditCard => 'Enter as positive amount (e.g., 5000 for MYR 5,000 outstanding balance)',
                                        default => 'Starting balance for this account'
                                    }
                            )
                            ->disabled(fn (Component $livewire) => $livewire instanceof EditAccount)
                            ->afterContent(
                                Action::make('adjustBalance')
                                    ->label('Adjust Balance')
                                    ->icon(Heroicon::Calculator)
                                    ->color('primary')
                                    ->size('sm')
                                    ->visible(fn (Component $livewire) => $livewire instanceof EditAccount)
                                    ->schema([
                                        TextInput::make('current_balance')
                                            ->label('Current Balance')
                                            ->prefix('MYR')
                                            ->disabled()
                                            ->default(fn (Component $livewire) => number_format($livewire->record->balance, 2)),

                                        TextInput::make('new_balance')
                                            ->label('New Balance')
                                            ->prefix('MYR')
                                            ->numeric()
                                            ->required()
                                            ->default(fn (Component $livewire) => $livewire->record->balance),

                                        Textarea::make('adjustment_note')
                                            ->label('Reason for Adjustment')
                                            ->placeholder('e.g., Bank reconciliation, correction, initial import adjustment')
                                            ->maxLength(500),
                                    ])
                                    ->modalHeading('Adjust Account Balance')
                                    ->modalDescription('This will create a balance adjustment transaction to change your account balance.')
                                    ->action(function (array $data, Component $livewire): void {
                                        $currentBalance = $livewire->record->balance;
                                        $newBalance = (float) $data['new_balance'];
                                        $adjustmentAmount = $newBalance - $currentBalance;

                                        if ($adjustmentAmount == 0) {
                                            Notification::make()
                                                ->title('No adjustment needed')
                                                ->body('The new balance is the same as the current balance.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        // Create adjustment transaction
                                        Transaction::create([
                                            'user_id' => auth()->id(),
                                            'account_id' => $livewire->record->id,
                                            'type' => $adjustmentAmount > 0 ? TransactionType::Income : TransactionType::Expense,
                                            'amount' => abs($adjustmentAmount),
                                            'description' => 'Balance Adjustment: '.($data['adjustment_note'] ?? 'Manual balance adjustment'),
                                            'date' => now(),
                                            'category_id' => null, // No category for adjustments
                                        ]);

                                        // Update the account balance
                                        $livewire->record->updateBalance();

                                        Notification::make()
                                            ->title('Balance adjusted successfully')
                                            ->body('Account balance changed from MYR '.number_format($currentBalance, 2).' to MYR '.number_format($newBalance, 2))
                                            ->success()
                                            ->send();

                                        // Refresh the form to show updated values
                                        $livewire->refreshFormData(['initial_balance', 'balance']);
                                    })
                            ),

                        TextInput::make('balance')
                            ->label(fn (?Account $record) => $record?->isLiability() ? 'Outstanding Balance' : 'Current Balance')
                            ->prefix(fn (?Account $record) => $record?->currency)
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Account $record) => $record ? number_format($record->balance, 2) : null)
                            ->visible(fn (Component $livewire) => $livewire instanceof EditAccount),

                        Select::make('currency')
                            ->required()
                            ->options([
                                'MYR' => 'MYR - Malaysian Ringgit',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                                'JPY' => 'JPY - Japanese Yen',
                            ])
                            ->default('MYR')
                            ->native(false),
                    ])->columns(2),

                Section::make('Additional Details')
                    ->schema([
                        TextInput::make('account_number')
                            ->maxLength(255),

                        ColorPicker::make('color')
                            ->required()
                            ->default('#3b82f6'),

                        Textarea::make('notes')
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->placeholder(fn (Get $get) => $get('type') === AccountType::Loan
                                ? 'e.g., Monthly payment: RM 1,200, Due on 15th of each month, Loan ref: HP123456'
                                : 'Additional notes about this account'),

                        Toggle::make('is_active')
                            ->required()
                            ->default(true)
                            ->label('Active Account'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge(),

                TextColumn::make('balance')
                    ->label('Balance/Outstanding')
                    ->money(fn (Account $record) => strtolower($record->currency))
                    ->sortable()
                    ->formatStateUsing(fn (Account $record) => $record->formatted_balance)
                    ->color(fn (Account $record) => $record->isLiability() ? 'danger' : ($record->balance >= 0 ? 'success' : 'danger')),

                TextColumn::make('currency')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('account_number')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(AccountType::class),

                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All accounts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort(function (Builder $query): Builder {
                return $query
                    ->orderByRaw("CASE type WHEN 'bank' THEN 0 WHEN 'cash' THEN 1 WHEN 'credit_card' THEN 2 WHEN 'loan' THEN 3 ELSE 4 END")
                    ->orderBy('sort_order');
            })
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('user_id', auth()->id())->count();
    }
}
