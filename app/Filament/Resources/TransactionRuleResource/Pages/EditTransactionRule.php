<?php

namespace App\Filament\Resources\TransactionRuleResource\Pages;

use App\Filament\Resources\TransactionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransactionRule extends EditRecord
{
    protected static string $resource = TransactionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
