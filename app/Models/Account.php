<?php

namespace App\Models;

use App\Casts\MoneyCast;
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
    ];

    protected $casts = [
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


    public function updateBalance(): void
    {
        $income = $this->transactions()
            ->where('type', 'income')
            ->sum('amount');

        $expenses = $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');

        $transfersOut = $this->transfersFrom()
            ->where('type', 'transfer')
            ->sum('amount');

        $transfersIn = $this->transfersTo()
            ->where('type', 'transfer')
            ->sum('amount');

        // The cast will handle conversion to/from cents
        $this->balance = ($this->getRawOriginal('initial_balance') + $income - $expenses - $transfersOut + $transfersIn) / 100;
        $this->save();
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'bank' => 'heroicon-o-building-library',
            'cash' => 'heroicon-o-banknotes',
            'credit_card' => 'heroicon-o-credit-card',
            'loan' => 'heroicon-o-document-text',
            default => 'heroicon-o-wallet',
        };
    }

    public function getFormattedBalanceAttribute(): string
    {
        if ($this->type === 'loan') {
            // For loans, show positive outstanding amount
            $outstanding = abs($this->balance);

            return $this->currency.' '.number_format($outstanding, 2);
        }

        return $this->currency.' '.number_format($this->balance, 2);
    }

    public function getBalanceLabelAttribute(): string
    {
        return $this->type === 'loan' ? 'Outstanding' : 'Balance';
    }

    public function isLoan(): bool
    {
        return $this->type === 'loan';
    }
}
