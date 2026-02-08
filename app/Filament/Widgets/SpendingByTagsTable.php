<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class SpendingByTagsTable extends TableWidget
{
    protected static ?int $sort = 9;

    protected static ?string $heading = 'Tag Breakdown';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Transaction::query()
            ->where('user_id', auth()->id())
            ->whereHas('tags', function ($query) {
                $query->where('type', 'user_'.auth()->id());
            })
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->getTableRecords())
            ->columns([
                TextColumn::make('name')
                    ->label('Tag')
                    ->badge()
                    ->color('primary'),

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
            ->whereHas('tags', function ($query) {
                $query->where('type', 'user_'.auth()->id());
            })
            ->join('taggables', function ($join) {
                $join->on('transactions.id', '=', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', Transaction::class);
            })
            ->join('tags', 'taggables.tag_id', '=', 'tags.id')
            ->select(
                'tags.id',
                'tags.name',
                DB::raw('COUNT(DISTINCT transactions.id) as count'),
                DB::raw('SUM(transactions.amount) as total')
            )
            ->groupBy('tags.id', 'tags.name')
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
