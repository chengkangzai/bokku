<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Payees\PayeeResource;
use App\Models\Payee;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopPayeesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Top Payees';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payee::query()
                    ->where('user_id', auth()->id())
                    ->where('total_amount', '>', 0)
                    ->orderByDesc('total_amount')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('name')
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('type')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('transactions_count')
                    ->counts('transactions')
                    ->label('Txns'),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('MYR'),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Payee $record): string => PayeeResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ])
            ->paginated(false)
            ->emptyStateHeading('No payee spending yet')
            ->emptyStateDescription('Assign payees to your expense transactions to see top spending here.');
    }

    public static function canView(): bool
    {
        return Payee::where('user_id', auth()->id())
            ->where('total_amount', '>', 0)
            ->exists();
    }
}
