<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Main Checking Account'),

                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'bank' => 'Bank Account',
                                'cash' => 'Cash',
                                'credit_card' => 'Credit Card',
                                'loan' => 'Loan',
                            ])
                            ->native(false),

                        Forms\Components\TextInput::make('initial_balance')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('RM')
                            ->label('Initial Balance'),

                        Forms\Components\Select::make('currency')
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

                Forms\Components\Section::make('Additional Details')
                    ->schema([
                        Forms\Components\TextInput::make('account_number')
                            ->maxLength(255)
                            ->placeholder('Last 4 digits for reference'),

                        Forms\Components\ColorPicker::make('color')
                            ->required()
                            ->default('#3b82f6'),

                        Forms\Components\Textarea::make('notes')
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'bank',
                        'success' => 'cash',
                        'warning' => 'credit_card',
                        'danger' => 'loan',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('balance')
                    ->money(fn (Account $record) => strtolower($record->currency))
                    ->sortable()
                    ->color(fn (Account $record) => $record->balance >= 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('currency')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('account_number')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'bank' => 'Bank Account',
                        'cash' => 'Cash',
                        'credit_card' => 'Credit Card',
                        'loan' => 'Loan',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All accounts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    public static function getRelations(): array
    {
        return [
            AccountResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('user_id', auth()->id())->count();
    }
}
