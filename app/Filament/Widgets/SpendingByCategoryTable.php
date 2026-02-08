<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SpendingByCategoryTable extends TableWidget
{
    protected static ?int $sort = 7;

    protected static ?string $heading = 'Category Breakdown';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->getTableRecords())
            ->columns([
                TextColumn::make('name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->color),

                TextColumn::make('count')
                    ->label('Transactions')
                    ->alignCenter(),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => 'MYR '.number_format($state / 100, 2))
                    ->alignEnd(),

                TextColumn::make('percentage')
                    ->label('%')
                    ->suffix('%')
                    ->alignEnd(),
            ])
            ->paginated(false);
    }

    public function getTableRecords(): \Illuminate\Support\Collection
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        $data = Transaction::query()
            ->where('transactions.user_id', auth()->id())
            ->where('transactions.type', TransactionType::Expense)
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'categories.id',
                'categories.name',
                'categories.color',
                DB::raw('COUNT(transactions.id) as count'),
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $totalAmount = $data->sum('total');

        return $data->map(function ($item) use ($totalAmount) {
            $item->percentage = $totalAmount > 0
                ? round(($item->total / $totalAmount) * 100, 1)
                : 0;

            return $item;
        });
    }
}
