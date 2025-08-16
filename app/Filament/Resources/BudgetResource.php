<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\BudgetResource\Pages\ListBudgets;
use App\Filament\Resources\BudgetResource\Pages\CreateBudget;
use App\Filament\Resources\BudgetResource\Pages\EditBudget;
use App\Filament\Resources\BudgetResource\Pages;
use App\Models\Budget;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static string | \UnitEnum | null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'category.name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Budget Details')
                    ->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->relationship(
                                'category',
                                'name',
                                fn (Builder $query) => $query
                                    ->where('user_id', auth()->id())
                                    ->where('type', 'expense')
                                    ->whereDoesntHave('budgets', fn ($q) => $q->where('user_id', auth()->id()))
                            )
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->helperText('Only expense categories without existing budgets are shown'),

                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->minValue(0.01)
                            ->helperText('Budget amount for the selected period'),

                        Select::make('period')
                            ->required()
                            ->options([
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'annual' => 'Annual',
                            ])
                            ->default('monthly')
                            ->native(false),

                        DatePicker::make('start_date')
                            ->required()
                            ->default(now()->startOfMonth())
                            ->helperText('When this budget period starts'),
                    ])->columns(2),

                Section::make('Budget Options')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable/disable budget tracking'),

                        Toggle::make('auto_rollover')
                            ->label('Auto Rollover')
                            ->helperText('Unused budget carries over to next period'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Budget')
                    ->formatStateUsing(fn ($state) => 'RM '.number_format($state, 2))
                    ->sortable(),

                TextColumn::make('spent')
                    ->label('Spent')
                    ->getStateUsing(fn (Budget $record): string => $record->getFormattedSpent())
                    ->color(fn (Budget $record): string => $record->getStatusColor()),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn (Budget $record): string => $record->getProgressPercentage().'%')
                    ->color(fn (Budget $record): string => $record->getStatusColor())
                    ->badge(),

                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->getStateUsing(fn (Budget $record): string => $record->getFormattedRemaining())
                    ->color(fn (Budget $record): string => $record->isOverBudget() ? 'danger' : 'success'),

                TextColumn::make('period')
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->options([
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'annual' => 'Annual',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'under' => 'Under Budget',
                        'near' => 'Near Limit',
                        'over' => 'Over Budget',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (! $data['value']) {
                            return $query;
                        }

                        // Get all budgets and filter by status in memory
                        // This is necessary because status is calculated based on transactions
                        $budgets = $query->get();
                        $filteredIds = $budgets->filter(function ($budget) use ($data) {
                            return $budget->getStatus() === $data['value'];
                        })->pluck('id');

                        return $query->whereIn('id', $filteredIds);
                    }),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('toggle_active')
                        ->label('Toggle Active')
                        ->icon('heroicon-m-power')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => ! $record->is_active]);
                            }
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('category.name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['category']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'create' => CreateBudget::route('/create'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $overBudgetCount = static::getEloquentQuery()
            ->get()
            ->filter(fn (Budget $budget) => $budget->isOverBudget())
            ->count();

        return $overBudgetCount > 0 ? (string) $overBudgetCount : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getNavigationBadge() ? 'danger' : null;
    }
}
