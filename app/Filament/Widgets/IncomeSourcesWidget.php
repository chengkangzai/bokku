<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncomeSourcesWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Income Sources This Month';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->getTableRecords())
            ->columns([
                TextColumn::make('name')
                    ->label('Category'),

                TextColumn::make('total')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'MYR '.number_format($state / 100, 2))
                    ->color('success')
                    ->alignEnd(),
            ])
            ->paginated(false);
    }

    public function getTableRecords(): Collection
    {
        return Transaction::query()
            ->where('transactions.user_id', auth()->id())
            ->where('transactions.type', TransactionType::Income)
            ->whereBetween('transactions.date', [now()->startOfMonth(), now()->endOfMonth()])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get();
    }
}
