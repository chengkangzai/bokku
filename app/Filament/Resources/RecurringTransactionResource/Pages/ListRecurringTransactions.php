<?php

namespace App\Filament\Resources\RecurringTransactionResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\RecurringTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecurringTransactions extends ListRecords
{
    protected static string $resource = RecurringTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
