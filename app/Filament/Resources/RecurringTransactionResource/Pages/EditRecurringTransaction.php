<?php

namespace App\Filament\Resources\RecurringTransactionResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\RecurringTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecurringTransaction extends EditRecord
{
    protected static string $resource = RecurringTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
