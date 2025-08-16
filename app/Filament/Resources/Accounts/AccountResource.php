<?php

namespace App\Filament\Resources\Accounts;

use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\RelationManagers\TransactionsRelationManager;
use App\Models\Account;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                            ->options([
                                'bank' => 'Bank Account',
                                'cash' => 'Cash',
                                'credit_card' => 'Credit Card',
                                'loan' => 'Loan',
                            ])
                            ->native(false),

                        TextInput::make('initial_balance')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('MYR')
                            ->label(fn (Get $get) => $get('type') === 'loan' ? 'Total Amount Owed' : 'Initial Balance')
                            ->helperText(fn (Get $get) => $get('type') === 'loan'
                                ? 'Enter as negative amount (e.g., -60000 for MYR 60,000 loan)'
                                : 'Starting balance for this account'),

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
                            ->maxLength(255)
                            ->placeholder('Last 4 digits for reference'),

                        ColorPicker::make('color')
                            ->required()
                            ->default('#3b82f6'),

                        Textarea::make('notes')
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->placeholder(fn (Get $get) => $get('type') === 'loan'
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

                BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'bank',
                        'success' => 'cash',
                        'warning' => 'credit_card',
                        'danger' => 'loan',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                TextColumn::make('balance')
                    ->label('Balance/Outstanding')
                    ->money(fn (Account $record) => strtolower($record->currency))
                    ->sortable()
                    ->formatStateUsing(fn (Account $record) => $record->formatted_balance)
                    ->color(fn (Account $record) => $record->type === 'loan' ? 'danger' : ($record->balance >= 0 ? 'success' : 'danger')),

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
                    ->options([
                        'bank' => 'Bank Account',
                        'cash' => 'Cash',
                        'credit_card' => 'Credit Card',
                        'loan' => 'Loan',
                    ]),

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
            ->defaultSort('name')
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
