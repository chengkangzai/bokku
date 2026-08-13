<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasColor, HasLabel
{
    case Bank = 'bank';
    case Cash = 'cash';
    case CreditCard = 'credit_card';
    case Loan = 'loan';
    case Investment = 'investment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Bank Account',
            self::Cash => 'Cash',
            self::CreditCard => 'Credit Card',
            self::Loan => 'Loan',
            self::Investment => 'Investment',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bank => 'primary',
            self::Cash => 'success',
            self::CreditCard => 'warning',
            self::Loan => 'danger',
            self::Investment => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Bank => 'heroicon-o-building-library',
            self::Cash => 'heroicon-o-banknotes',
            self::CreditCard => 'heroicon-o-credit-card',
            self::Loan => 'heroicon-o-document-text',
            self::Investment => 'heroicon-o-arrow-trending-up',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function assetTypes(): array
    {
        return [self::Bank, self::Cash, self::Investment];
    }

    public function isLiability(): bool
    {
        return match ($this) {
            self::Loan, self::CreditCard => true,
            self::Bank, self::Cash, self::Investment => false,
        };
    }
}
