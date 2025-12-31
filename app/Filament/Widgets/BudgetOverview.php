<?php

namespace App\Filament\Widgets;

use App\Models\Budget;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class BudgetOverview extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Budget Overview';

    protected int|string|array $columnSpan = '1';

    public static function canView(): bool
    {
        return Budget::where('user_id', auth()->id())
            ->where('is_active', true)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Budget::query()
                    ->where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->with(['category'])
                    ->orderBy('created_at')
            )
            ->columns([
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('period')
                    ->badge()
                    ->label('Period')
                    ->colors([
                        'secondary' => 'weekly',
                        'primary' => 'monthly',
                        'success' => 'annual',
                    ]),

                TextColumn::make('amount')
                    ->label('Budget')
                    ->formatStateUsing(fn (Budget $record): string => $record->getFormattedBudget())
                    ->sortable(),

                TextColumn::make('getFormattedSpent')
                    ->label('Spent')
                    ->getStateUsing(fn (Budget $record): string => $record->getFormattedSpent())
                    ->color(fn (Budget $record): string => $record->getStatusColor()),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn (Budget $record): string => $record->getProgressPercentage().'%')
                    ->color(fn (Budget $record): string => $record->getStatusColor()),

                TextColumn::make('status')
                    ->badge()
                    ->label('Status')
                    ->getStateUsing(fn (Budget $record): string => match ($record->getStatus()) {
                        'over' => 'Over Budget',
                        'near' => 'Near Limit',
                        'under' => 'On Track',
                        default => 'Unknown',
                    })
                    ->colors([
                        'danger' => 'Over Budget',
                        'warning' => 'Near Limit',
                        'success' => 'On Track',
                    ]),

                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->getStateUsing(fn (Budget $record): string => $record->getFormattedRemaining())
                    ->color(fn (Budget $record): string => $record->isOverBudget() ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Budget $record): string => route('filament.admin.resources.budgets.edit', $record))
                    ->icon('heroicon-m-eye'),
            ])
            ->emptyStateHeading('No active budgets')
            ->emptyStateDescription('Get started by creating your first budget')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create Budget')
                    ->url(route('filament.admin.resources.budgets.create'))
                    ->icon('heroicon-m-plus')
                    ->button(),
            ])
            ->paginated(false);
    }
}
