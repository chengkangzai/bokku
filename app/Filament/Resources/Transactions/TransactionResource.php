<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\Tag;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                Grid::make(1)
                    ->schema([
                        Section::make('Transaction Details')
                            ->schema([
                                Radio::make('type')
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

                                TextInput::make('amount')
                                    ->required()
                                    ->numeric()
                                    ->prefix('RM')
                                    ->minValue(0.01)
                                    ->reactive()
                                    ->helperText(function (Get $get, $state) {
                                        $accountId = $get('account_id');
                                        $type = $get('type');
                                        $amount = (float) $state;

                                        if (! $accountId || ! $type || ! $amount) {
                                            return null;
                                        }

                                        $account = Account::find($accountId);

                                        if (! $account) {
                                            return null;
                                        }

                                        return $account->getBalanceWarningMessage($amount, $type);
                                    }),

                                DatePicker::make('date')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),

                                TextInput::make('description')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Grocery shopping at Walmart'),
                            ])->columns([
                                'default' => 1,
                                'sm' => 2,
                            ]),

                        Section::make('Accounts & Category')
                            ->schema([
                                Select::make('account_id')
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

                                Select::make('to_account_id')
                                    ->label('To Account')
                                    ->relationship(
                                        'toAccount',
                                        'name',
                                        fn (Builder $query) => $query->where('user_id', auth()->id())->where('is_active', true)
                                    )
                                    ->required()
                                    ->native(false)
                                    ->visible(fn (Get $get) => $get('type') === 'transfer'),

                                Select::make('category_id')
                                    ->relationship(
                                        'category',
                                        'name',
                                        fn (Builder $query, Get $get) => $query->where('user_id', auth()->id())
                                            ->when($get('type'), fn ($q, $type) => $q->where('type', $type))
                                    )
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->name)
                                    ->helperText(function (Get $get, $state) {
                                        $categoryId = $state;
                                        $amount = (float) $get('amount');
                                        $type = $get('type');

                                        if (! $categoryId || ! $amount || $type !== 'expense') {
                                            return null;
                                        }

                                        $category = Category::find($categoryId);

                                        if (! $category) {
                                            return null;
                                        }

                                        return $category->getBudgetWarning($amount);
                                    })
                                    ->createOptionForm(fn (Get $get) => [
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Groceries, Salary'),
                                        Select::make('type')
                                            ->required()
                                            ->options([
                                                'income' => 'Income',
                                                'expense' => 'Expense',
                                            ])
                                            ->default($get('type'))
                                            ->disabled()
                                            ->dehydrated(),
                                        ColorPicker::make('color')
                                            ->required()
                                            ->default('#6b7280'),
                                        Hidden::make('user_id')
                                            ->default(auth()->id()),
                                        Hidden::make('sort_order')
                                            ->default(0),
                                    ])
                                    ->createOptionUsing(function (array $data, Get $get) {
                                        $data['user_id'] = auth()->id();
                                        $data['type'] = $get('type');

                                        return Category::create($data)->getKey();
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

                Grid::make(1)
                    ->schema([
                        Section::make('Attachments')
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

                        Section::make('Additional Information')
                            ->schema([
                                TextInput::make('reference')
                                    ->maxLength(255)
                                    ->placeholder('Check number, invoice #, etc.'),

                                Textarea::make('notes')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),

                                SpatieTagsInput::make('tags')
                                    ->type(fn () => 'user_'.auth()->id())
                                    ->suggestions(function () {
                                        return Tag::getWithType('user_'.auth()->id())->pluck('name');
                                    })
                                    ->columnSpanFull()
                                    ->placeholder('Add tags to organize transactions'),

                                Toggle::make('is_reconciled')
                                    ->label('Reconciled')
                                    ->helperText('Mark as reconciled when verified against bank statement'),
                            ]),

                        Section::make('Automation')
                            ->schema([
                                TextEntry::make('matching_rules')
                                    ->label('Matching Rules')
                                    ->state(function ($get) {
                                        $description = $get('description');
                                        $amount = $get('amount');
                                        $type = $get('type');

                                        if (! $description && ! $amount) {
                                            return 'Enter description or amount to see matching rules';
                                        }

                                        // Find matching rules
                                        $rules = TransactionRule::where('user_id', auth()->id())
                                            ->where('is_active', true)
                                            ->where(function ($query) use ($type) {
                                                $query->where('apply_to', 'all')
                                                    ->orWhere('apply_to', $type);
                                            })
                                            ->orderBy('priority', 'desc')
                                            ->get();

                                        $matchingRules = [];
                                        foreach ($rules as $rule) {
                                            // Create a temporary transaction object for matching
                                            $tempTransaction = new Transaction([
                                                'description' => $description ?? '',
                                                'amount' => $amount ?? 0,
                                                'type' => $type ?? 'expense',
                                                'category_id' => $get('category_id'),
                                                'user_id' => auth()->id(),
                                            ]);

                                            if ($rule->matches($tempTransaction)) {
                                                $matchingRules[] = $rule->name;
                                            }
                                        }

                                        if (empty($matchingRules)) {
                                            return 'No matching rules found';
                                        }

                                        return '✓ Will apply: '.implode(', ', $matchingRules);
                                    })
                                    ->helperText('Rules will apply automatically when saving')
                                    ->visible(fn ($operation) => $operation === 'create'),
                            ])
                            ->visible(fn ($operation) => $operation === 'create'),
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 1,
                    ]),
            ]);
    }

    public static function table(Table $table): Table
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
                    ]),

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
                    }),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->sortable()
                    ->visible(fn () => true),

                TextColumn::make('category.name')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('toAccount.name')
                    ->label('To')
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_reconciled')
                    ->boolean()
                    ->label('✓'),

                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('appliedRule.name')
                    ->label('Applied Rule')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                SpatieTagsColumn::make('tags')
                    ->type(fn () => 'user_'.auth()->id())
                    ->toggleable(),

                SpatieMediaLibraryImageColumn::make('receipts')
                    ->collection('receipts')
                    ->label('Attachments')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                        'transfer' => 'Transfer',
                    ]),

                SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship(
                        'account',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship(
                        'category',
                        'name',
                        fn (Builder $query) => $query->where('user_id', auth()->id())
                    ),

                TernaryFilter::make('is_reconciled')
                    ->label('Reconciled')
                    ->placeholder('All transactions')
                    ->trueLabel('Reconciled only')
                    ->falseLabel('Unreconciled only'),
            ])
            ->recordActions([
                Action::make('apply_rules')
                    ->label('Apply Rules')
                    ->icon('heroicon-o-sparkles')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Apply Rules')
                    ->modalDescription('This will apply matching automation rules to this transaction.')
                    ->action(function ($record) {
                        TransactionRule::applyRules($record);
                        $record->refresh();

                        if ($record->applied_rule_id) {
                            Notification::make()
                                ->title('Rules Applied')
                                ->success()
                                ->body("Applied rule: {$record->appliedRule->name}")
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No Rules Applied')
                                ->warning()
                                ->body('No matching rules found for this transaction.')
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => ! $record->applied_rule_id),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('apply_rules_bulk')
                        ->label('Apply Rules')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Apply Rules to Selected Transactions')
                        ->modalDescription('This will apply matching automation rules to all selected transactions.')
                        ->action(function ($records) {
                            $applied = 0;
                            $skipped = 0;

                            foreach ($records as $transaction) {
                                if (! $transaction->applied_rule_id) {
                                    TransactionRule::applyRules($transaction);
                                    $transaction->refresh();

                                    if ($transaction->applied_rule_id) {
                                        $applied++;
                                    } else {
                                        $skipped++;
                                    }
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title('Rules Applied')
                                ->success()
                                ->body("Applied rules to {$applied} transaction(s). Skipped {$skipped}.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
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
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'edit' => EditTransaction::route('/{record}/edit'),
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
