<?php

namespace App\Filament\Resources\RecurringTransactionResource\Pages;

use App\Filament\Resources\RecurringTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecurringTransaction extends CreateRecord
{
    protected static string $resource = RecurringTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Set default start_date if not provided
        if (empty($data['start_date'])) {
            $data['start_date'] = now();
        }

        // Set initial next_date if not provided
        if (empty($data['next_date'])) {
            $data['next_date'] = $data['start_date'];
        }

        return $data;
    }
}
