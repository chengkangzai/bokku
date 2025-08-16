<?php

namespace App\Filament\Resources\TransactionRules\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\TransactionRules\TransactionRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransactionRule extends EditRecord
{
    protected static string $resource = TransactionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
