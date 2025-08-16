<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TransactionRuleResource\Pages\ListTransactionRules;
use App\Filament\Resources\TransactionRuleResource\Pages\CreateTransactionRule;
use App\Filament\Resources\TransactionRuleResource\Pages\EditTransactionRule;
use App\Filament\Resources\TransactionRuleResource\Pages;
use App\Models\TransactionRule;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionRuleResource extends Resource
{
    protected static ?string $model = TransactionRule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string | \UnitEnum | null $navigationGroup = 'Automation';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Rule Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Categorize Starbucks'),

                        Textarea::make('description')
                            ->rows(2)
                            ->placeholder('Optional description of what this rule does'),

                        Select::make('apply_to')
                            ->label('Apply To')
                            ->options([
                                'all' => 'All Transactions',
                                'income' => 'Income Only',
                                'expense' => 'Expense Only',
                                'transfer' => 'Transfer Only',
                            ])
                            ->default('all')
                            ->required(),

                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority rules run first'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('stop_processing')
                            ->label('Stop Processing Other Rules')
                            ->helperText('If this rule matches, don\'t apply any other rules')
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make('Conditions')
                    ->description('All conditions must match for the rule to apply')
                    ->schema([
                        Repeater::make('conditions')
                            ->schema([
                                Select::make('field')
                                    ->options([
                                        'description' => 'Description',
                                        'amount' => 'Amount',
                                        'category_id' => 'Category',
                                    ])
                                    ->required()
                                    ->reactive(),

                                Select::make('operator')
                                    ->options(fn (Get $get) => match ($get('field')) {
                                        'description' => [
                                            'contains' => 'Contains',
                                            'not_contains' => 'Does not contain',
                                            'equals' => 'Equals',
                                            'not_equals' => 'Does not equal',
                                            'starts_with' => 'Starts with',
                                            'ends_with' => 'Ends with',
                                            'regex' => 'Matches regex',
                                        ],
                                        'amount' => [
                                            'equals' => 'Equals',
                                            'not_equals' => 'Does not equal',
                                            'greater_than' => 'Greater than',
                                            'less_than' => 'Less than',
                                            'greater_than_or_equal' => 'Greater than or equal',
                                            'less_than_or_equal' => 'Less than or equal',
                                        ],
                                        'category_id' => [
                                            'equals' => 'Is',
                                            'not_equals' => 'Is not',
                                        ],
                                        default => []
                                    })
                                    ->required(),

                                TextInput::make('value')
                                    ->label('Value')
                                    ->required()
                                    ->visible(fn (Get $get) => $get('field') !== 'category_id'),

                                Select::make('value')
                                    ->label('Category')
                                    ->options(fn () => auth()->user()->categories()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->visible(fn (Get $get) => $get('field') === 'category_id'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Add Condition')
                            ->minItems(1),
                    ]),

                Section::make('Actions')
                    ->description('What to do when conditions match')
                    ->schema([
                        Repeater::make('actions')
                            ->schema([
                                Select::make('type')
                                    ->label('Action Type')
                                    ->options([
                                        'set_category' => 'Set Category',
                                        'add_tag' => 'Add Tag',
                                        'set_notes' => 'Set Notes',
                                    ])
                                    ->required()
                                    ->reactive(),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(fn () => auth()->user()->categories()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === 'set_category'),

                                TextInput::make('tag')
                                    ->label('Tag')
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === 'add_tag')
                                    ->placeholder('e.g., subscription'),

                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->required()
                                    ->visible(fn (Get $get) => $get('type') === 'set_notes')
                                    ->placeholder('Notes to add to the transaction'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Add Action')
                            ->minItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('apply_to')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'all' => 'gray',
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'info',
                    }),

                TextColumn::make('conditions')
                    ->label('Conditions')
                    ->formatStateUsing(fn ($state) => count($state).' condition(s)')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('actions')
                    ->label('Actions')
                    ->formatStateUsing(fn ($state) => count($state).' action(s)')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('priority')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                IconColumn::make('stop_processing')
                    ->label('Stop')
                    ->boolean()
                    ->tooltip('Stops processing other rules'),

                TextColumn::make('times_applied')
                    ->label('Applied')
                    ->formatStateUsing(fn ($state) => number_format($state).' times')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === 0 => 'gray',
                        $state < 10 => 'info',
                        $state < 50 => 'success',
                        default => 'warning'
                    })
                    ->sortable(),

                TextColumn::make('last_applied_at')
                    ->label('Last Applied')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->description(fn ($state) => $state ? 'Last used '.$state->diffForHumans() : 'Never used')
                    ->color(fn ($state) => ! $state ? 'gray' : 'primary')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),

                SelectFilter::make('apply_to')
                    ->options([
                        'all' => 'All Transactions',
                        'income' => 'Income Only',
                        'expense' => 'Expense Only',
                        'transfer' => 'Transfer Only',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (TransactionRule $record) {
                        $newRule = $record->replicate();
                        $newRule->name = $record->name.' (Copy)';
                        $newRule->times_applied = 0;
                        $newRule->last_applied_at = null;
                        $newRule->save();

                        Notification::make()
                            ->title('Rule Duplicated')
                            ->success()
                            ->body("Created copy: {$newRule->name}")
                            ->send();
                    }),
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-beaker')
                    ->color('info')
                    ->modalHeading('Test Rule')
                    ->modalDescription('See which recent transactions would match this rule')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (TransactionRule $record) => view('filament.resources.transaction-rule.test-rule', [
                        'rule' => $record,
                        'transactions' => auth()->user()->transactions()
                            ->with(['category', 'account'])
                            ->latest()
                            ->limit(20)
                            ->get()
                            ->filter(fn ($transaction) => $record->matches($transaction)),
                    ])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('apply_to_all')
                    ->label('Apply to All Transactions')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Apply Rules to All Transactions')
                    ->modalDescription('This will apply the selected rules to all your existing transactions.')
                    ->action(function ($records) {
                        $totalApplied = 0;
                        $transactions = auth()->user()->transactions()->get();

                        foreach ($records as $rule) {
                            foreach ($transactions as $transaction) {
                                if ($rule->matches($transaction)) {
                                    $rule->apply($transaction);
                                    $totalApplied++;
                                }
                            }
                        }

                        Notification::make()
                            ->title('Rules Applied')
                            ->success()
                            ->body("Applied rules to {$totalApplied} transactions.")
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc')
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
            'index' => ListTransactionRules::route('/'),
            'create' => CreateTransactionRule::route('/create'),
            'edit' => EditTransactionRule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
