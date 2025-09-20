<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Transfer => 'Transfer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Income => 'success',
            self::Expense => 'danger',
            self::Transfer => 'primary',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Income => 'heroicon-o-arrow-down-circle',
            self::Expense => 'heroicon-o-arrow-up-circle',
            self::Transfer => 'heroicon-o-arrow-right-circle',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Income => 'Money coming in',
            self::Expense => 'Money going out',
            self::Transfer => 'Move between accounts',
        };
    }

    public function getFormattedLabel(): string
    {
        return match ($this) {
            self::Income => '💰 Income',
            self::Expense => '💸 Expense',
            self::Transfer => '🔄 Transfer',
        };
    }

    public function getAmountPrefix(): string
    {
        return match ($this) {
            self::Income => '+',
            self::Expense => '-',
            self::Transfer => '',
        };
    }
}