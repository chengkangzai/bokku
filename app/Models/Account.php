<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AccountType;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'icon',
        'color',
        'balance',
        'initial_balance',
        'currency',
        'account_number',
        'notes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'balance' => MoneyCast::class,
        'initial_balance' => MoneyCast::class,
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(Transaction::class, 'from_account_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id');
    }

    public function allTransactions()
    {
        return Transaction::query()
            ->where(function ($query) {
                $query->where('account_id', $this->id)
                    ->orWhere('from_account_id', $this->id)
                    ->orWhere('to_account_id', $this->id);
            });
    }

    public function updateBalance(): void
    {
        $income = $this->transactions()
            ->where('type', TransactionType::Income)
            ->sum('amount');

        $expenses = $this->transactions()
            ->where('type', TransactionType::Expense)
            ->sum('amount');

        $transfersOut = $this->transfersFrom()
            ->where('type', TransactionType::Transfer)
            ->sum('amount');

        $transfersIn = $this->transfersTo()
            ->where('type', TransactionType::Transfer)
            ->sum('amount');

        // The cast will handle conversion to/from cents
        $this->balance = ($this->getRawOriginal('initial_balance') + $income - $expenses - $transfersOut + $transfersIn) / 100;
        $this->save();
    }

    protected function formattedBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->currency.' '.number_format($this->balance, 2)
        );
    }

    public function isLoan(): bool
    {
        return $this->type === AccountType::Loan;
    }

    public function isLiability(): bool
    {
        return $this->type?->isLiability() ?? false;
    }

    protected function balanceLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isLiability() ? 'Outstanding' : 'Balance'
        );
    }

    public function hasSufficientBalance(float $amount): bool
    {
        // For loan accounts, spending increases the outstanding amount (more negative)
        // For regular accounts, check if balance is sufficient
        if ($this->type === AccountType::Loan) {
            // Loans can always go more negative (no credit limit check for now)
            return true;
        }

        return $this->balance >= $amount;
    }

    public function getBalanceWarningMessage(float $amount, TransactionType $transactionType): ?string
    {
        if ($transactionType === TransactionType::Income) {
            return null; // No warning needed for income
        }

        $currentBalance = $this->balance;
        $afterBalance = $currentBalance - $amount;

        if ($this->type === AccountType::Loan) {
            $currentOutstanding = abs($currentBalance);
            $newOutstanding = abs($afterBalance);

            return "Current outstanding: {$this->currency} ".number_format($currentOutstanding, 2).
                   " → New outstanding: {$this->currency} ".number_format($newOutstanding, 2);
        }

        $balanceText = "Current balance: {$this->currency} ".number_format($currentBalance, 2);

        if (! $this->hasSufficientBalance($amount)) {
            $shortage = $amount - $currentBalance;

            return "⚠️ Insufficient funds! {$balanceText} (Short by {$this->currency} ".number_format($shortage, 2).')';
        }

        return $balanceText." → After transaction: {$this->currency} ".number_format($afterBalance, 2);
    }
}
