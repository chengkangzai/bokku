<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PayeeType: string implements HasColor, HasLabel
{
    case Merchant = 'merchant';
    case Person = 'person';
    case Company = 'company';
    case Government = 'government';
    case Utility = 'utility';

    public function getLabel(): string
    {
        return match ($this) {
            self::Merchant => 'Merchant',
            self::Person => 'Person',
            self::Company => 'Company',
            self::Government => 'Government',
            self::Utility => 'Utility',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Merchant => 'primary',
            self::Person => 'success',
            self::Company => 'info',
            self::Government => 'warning',
            self::Utility => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Merchant => 'heroicon-o-building-storefront',
            self::Person => 'heroicon-o-user',
            self::Company => 'heroicon-o-building-office-2',
            self::Government => 'heroicon-o-building-library',
            self::Utility => 'heroicon-o-bolt',
        };
    }
}
