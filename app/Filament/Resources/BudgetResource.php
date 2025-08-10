<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetResource\Pages;
use App\Models\Budget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'category.name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Budget Details')
                    ->schema([
                        Forms\Components\Select::make('category_id')
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

                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->prefix('RM')
                            ->minValue(0.01)
                            ->helperText('Budget amount for the selected period'),

                        Forms\Components\Select::make('period')
                            ->required()
                            ->options([
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                                'annual' => 'Annual',
                            ])
                            ->default('monthly')
                            ->native(false),

                        Forms\Components\DatePicker::make('start_date')
                            ->required()
                            ->default(now()->startOfMonth())
                            ->helperText('When this budget period starts'),
                    ])->columns(2),

                Forms\Components\Section::make('Budget Options')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable/disable budget tracking'),

                        Forms\Components\Toggle::make('auto_rollover')
                            ->label('Auto Rollover')
                            ->helperText('Unused budget carries over to next period'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Budget')
                    ->formatStateUsing(fn ($state) => 'RM '.number_format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('spent')
                    ->label('Spent')
                    ->getStateUsing(fn (Budget $record): string => $record->getFormattedSpent())
                    ->color(fn (Budget $record): string => $record->getStatusColor()),

                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn (Budget $record): string => $record->getProgressPercentage().'%')
                    ->color(fn (Budget $record): string => $record->getStatusColor())
                    ->badge(),

                Tables\Columns\TextColumn::make('remaining')
                    ->label('Remaining')
                    ->getStateUsing(fn (Budget $record): string => $record->getFormattedRemaining())
                    ->color(fn (Budget $record): string => $record->isOverBudget() ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('period')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('period')
                    ->options([
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'annual' => 'Annual',
                    ]),

                Tables\Filters\SelectFilter::make('status')
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

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('toggle_active')
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
            'index' => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
            'edit' => Pages\EditBudget::route('/{record}/edit'),
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
