<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make([
                    'default' => 1,
                    'lg' => 3,
                ])
                    ->schema([
                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\Section::make('Transaction Details')
                                    ->schema([
                                        Forms\Components\Radio::make('type')
                                            ->required()
                                            ->options([
                                                'income' => '💰 Income',
                                                'expense' => '💸 Expense',
                                                'transfer' => '🔄 Transfer',
                                            ])
                                            ->inline()
                                            ->default('expense')
                                            ->inlineLabel(false)
                                            ->descriptions([
                                                'income' => 'Money coming in',
                                                'expense' => 'Money going out',
                                                'transfer' => 'Move between accounts',
                                            ])
                                            ->reactive()
                                            ->afterStateUpdated(fn (callable $set) => $set('category_id', null))
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('amount')
                                            ->required()
                                            ->numeric()
                                            ->prefix('RM')
                                            ->minValue(0.01)
                                            ->reactive()
                                            ->helperText(function (Get $get, $state) {
                                                $accountId = $get('account_id');
                                                $type = $get('type');
                                                $amount = (float) $state;
                                                
                                                if (!$accountId || !$type || !$amount) {
                                                    return null;
                                                }
                                                
                                                $account = \App\Models\Account::find($accountId);
                                                
                                                if (!$account) {
                                                    return null;
                                                }
                                                
                                                return $account->getBalanceWarningMessage($amount, $type);
                                            }),

                                        Forms\Components\DatePicker::make('date')
                                            ->required()
                                            ->default(now())
                                            ->maxDate(now()),

                                        Forms\Components\TextInput::make('description')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Grocery shopping at Walmart'),
                                    ])->columns([
                                        'default' => 1,
                                        'sm' => 2,
                                    ]),

                                Forms\Components\Section::make('Accounts & Category')
                                    ->schema([
                                        Forms\Components\Select::make('account_id')
                                            ->label(fn (Get $get) => match ($get('type')) {
                                                'income' => 'To Account',
                                                'expense', 'transfer' => 'From Account',
                                                default => 'Account'
                                            })
                                            ->relationship(
                                                'account',
                                                'name',
                                                fn (Builder $query) => $query->where('user_id', auth()->id())->where('is_active', true)
                                            )
                                            ->required()
                                            ->native(false)
                                            ->reactive()
                                            ->visible(fn (Get $get) => ! empty($get('type')) && in_array($get('type'), ['income', 'expense', 'transfer']))
                                            ->helperText(fn (Get $get) => empty($get('type')) ? 'Please select a transaction type first' : null
                                            ),

                                        Forms\Components\Select::make('to_account_id')
                                            ->label('To Account')
                                            ->relationship(
                                                'toAccount',
                                                'name',
                                                fn (Builder $query) => $query->where('user_id', auth()->id())->where('is_active', true)
                                            )
                                            ->required()
                                            ->native(false)
                                            ->visible(fn (Get $get) => $get('type') === 'transfer'),

                                        Forms\Components\Select::make('category_id')
                                            ->relationship(
                                                'category',
                                                'name',
                                                fn (Builder $query, Get $get) => $query->where('user_id', auth()->id())
                                                    ->when($get('type'), fn ($q, $type) => $q->where('type', $type))
                                            )
                                            ->native(false)
                                            ->searchable()
                                            ->preload()
                                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                                            ->createOptionForm(fn (Get $get) => [
                                                Forms\Components\TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('e.g., Groceries, Salary'),
                                                Forms\Components\Select::make('type')
                                                    ->required()
                                                    ->options([
                                                        'income' => 'Income',
                                                        'expense' => 'Expense',
                                                    ])
                                                    ->default($get('type'))
                                                    ->disabled()
                                                    ->dehydrated(),
                                                Forms\Components\ColorPicker::make('color')
                                                    ->required()
                                                    ->default('#6b7280'),
                                                Forms\Components\Hidden::make('user_id')
                                                    ->default(auth()->id()),
                                                Forms\Components\Hidden::make('sort_order')
                                                    ->default(0),
                                            ])
                                            ->createOptionUsing(function (array $data, Get $get) {
                                                $data['user_id'] = auth()->id();
                                                $data['type'] = $get('type');

                                                return \App\Models\Category::create($data)->getKey();
                                            })
                                            ->createOptionModalHeading('Create New Category')
                                            ->visible(fn (Get $get) => ! empty($get('type')) && in_array($get('type'), ['income', 'expense'])),
                                    ])
                                    ->columns(2)
                                    ->description(fn (Get $get) => empty($get('type')) ? 'Select a transaction type to see available options' : null),

                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 2,
                            ]),

                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\Section::make('Attachments')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('receipts')
                                            ->collection('receipts')
                                            ->multiple()
                                            ->reorderable()
                                            ->maxFiles(5)
                                            ->acceptedFileTypes([
                                                'image/jpeg',
                                                'image/png',
                                                'image/gif',
                                                'image/webp',
                                                'application/pdf',
                                            ])
                                            ->maxSize(5120) // 5MB in KB
                                            ->label('Upload Receipts')
                                            ->helperText('Upload receipts, invoices, or related documents (max 5 files, 5MB each)')
                                            ->columnSpanFull()
                                            ->conversion('thumb'),
                                    ]),

                                Forms\Components\Section::make('Additional Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('reference')
                                            ->maxLength(255)
                                            ->placeholder('Check number, invoice #, etc.'),

                                        Forms\Components\Textarea::make('notes')
                                            ->maxLength(65535)
                                            ->columnSpanFull(),

                                        Forms\Components\Toggle::make('is_reconciled')
                                            ->label('Reconciled')
                                            ->helperText('Mark as reconciled when verified against bank statement'),
                                    ]),
                            ])
                            ->columnSpan([
                                'default' => 1,
                                'lg' => 1,
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
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
                    ]),

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
                    }),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->visible(fn () => true),

                Tables\Columns\TextColumn::make('category.name')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('toAccount.name')
                    ->label('To')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_reconciled')
                    ->boolean()
                    ->label('✓'),

                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                SpatieMediaLibraryImageColumn::make('receipts')
                    ->collection('receipts')
                    ->label('Attachments')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                        'transfer' => 'Transfer',
                    ]),

                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship(
                        'account',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship(
                        'category',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                Tables\Filters\TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->placeholder('All transactions')
                    ->trueLabel('Reconciled only')
                    ->falseLabel('Unreconciled only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc')
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('user_id', auth()->id())
            ->whereDate('date', today())
            ->count();

        return $count > 0 ? $count : null;
    }
}
