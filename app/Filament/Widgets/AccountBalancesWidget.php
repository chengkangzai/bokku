<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AccountBalancesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Account Balances';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Account::query()
                    ->where('user_id', auth()->id())
                    ->orderByDesc('balance')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Account')
                    ->description(fn (Account $record): string => $record->type->getLabel()),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => 'MYR '.number_format($state / 100, 2))
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->alignEnd(),
            ])
            ->paginated(false);
    }
}
