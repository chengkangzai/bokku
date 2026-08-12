<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TransactionType;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->is_admin;
        }

        return true;
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    protected function totalAssets(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) ($this->accounts()
                ->whereNotIn('type', ['loan', 'credit_card'])
                ->sum('balance') / 100)
        );
    }

    protected function totalLiabilities(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) ($this->accounts()
                ->whereIn('type', ['loan', 'credit_card'])
                ->sum('balance') / 100)
        );
    }

    protected function netWorth(): Attribute
    {
        return Attribute::make(
            get: fn (): float => (float) ($this->total_assets - $this->total_liabilities)
        );
    }

    /**
     * Reconstruct net worth at the end of each of the last $months months.
     *
     * Replays every transaction on top of each account's initial_balance using the
     * same formula as Account::updateBalance(), so the series always reconciles with
     * the stored balances. The final bucket applies every transaction regardless of
     * date, guaranteeing it equals the current net worth even with future-dated rows.
     *
     * Loads the user's full transaction history because initial_balance is the baseline.
     *
     * @return array<string, float> Keyed by 'Y-m', oldest first
     */
    public function netWorthHistory(int $months = 12): array
    {
        $accounts = $this->accounts()->get(['id', 'type', 'initial_balance']);

        $signs = $accounts->mapWithKeys(
            fn (Account $account): array => [$account->id => $account->isLiability() ? -1 : 1]
        );

        $running = $accounts->sum(
            fn (Account $account): int => $signs[$account->id] * (int) $account->getRawOriginal('initial_balance')
        );

        $cutoffs = collect(range($months - 1, 0))
            ->mapWithKeys(function (int $monthsAgo): array {
                $month = now()->subMonths($monthsAgo);

                return [$month->format('Y-m') => $month->endOfMonth()];
            });

        $transactions = $this->transactions()
            ->orderBy('date')
            ->get(['date', 'type', 'amount', 'account_id', 'from_account_id', 'to_account_id']);

        $history = [];
        $index = 0;

        foreach ($cutoffs as $key => $cutoff) {
            while ($index < $transactions->count() && $transactions[$index]->date->lte($cutoff)) {
                $running += $this->netWorthEffect($transactions[$index], $signs);
                $index++;
            }

            $history[$key] = (float) ($running / 100);
        }

        while ($index < $transactions->count()) {
            $running += $this->netWorthEffect($transactions[$index], $signs);
            $index++;
        }

        $history[array_key_last($history)] = (float) ($running / 100);

        return $history;
    }

    /**
     * With liabilities stored as positive outstanding amounts, every transaction moves
     * net worth by the same amount regardless of account type: income on a liability
     * shrinks the debt exactly as income on an asset grows the funds, and a transfer
     * between two of the user's accounts nets to zero.
     *
     * @param  Collection<int, int>  $signs
     */
    protected function netWorthEffect(Transaction $transaction, Collection $signs): int
    {
        $amount = (int) $transaction->getRawOriginal('amount');

        if ($transaction->type === TransactionType::Transfer) {
            return ($signs->has($transaction->to_account_id) ? $amount : 0)
                - ($signs->has($transaction->from_account_id) ? $amount : 0);
        }

        if (! $signs->has($transaction->account_id)) {
            return 0;
        }

        return $transaction->type === TransactionType::Income
            ? $amount
            : -$amount;
    }
}
