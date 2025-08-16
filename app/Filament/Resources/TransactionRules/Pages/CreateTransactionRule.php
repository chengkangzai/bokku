<?php

namespace App\Filament\Resources\TransactionRules\Pages;

use App\Filament\Resources\TransactionRules\TransactionRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransactionRule extends CreateRecord
{
    protected static string $resource = TransactionRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
