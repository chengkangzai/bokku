<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\HasUserScopedTags;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Tags\HasTags;

class Transaction extends Model implements HasMedia
{
    use HasFactory, HasTags, HasUserScopedTags, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'date',
        'account_id',
        'category_id',
        'recurring_transaction_id',
        'applied_rule_id',
        'from_account_id',
        'to_account_id',
        'reference',
        'notes',
        'is_reconciled',
    ];

    protected $casts = [
        'amount' => MoneyCast::class,
        'date' => 'date',
        'is_reconciled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->updateAccountBalances();

            // Apply rules to new transactions (but not those from recurring transactions)
            if (! $transaction->recurring_transaction_id) {
                TransactionRule::applyRules($transaction);
            }
        });

        static::updated(function (Transaction $transaction) {
            $transaction->updateAccountBalances();
        });

        static::deleted(function (Transaction $transaction) {
            $transaction->updateAccountBalances();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function appliedRule(): BelongsTo
    {
        return $this->belongsTo(TransactionRule::class, 'applied_rule_id');
    }

    public function updateAccountBalances(): void
    {
        if ($this->type === 'transfer') {
            if ($this->fromAccount) {
                $this->fromAccount->updateBalance();
            }
            if ($this->toAccount) {
                $this->toAccount->updateBalance();
            }
        } else {
            if ($this->account) {
                $this->account->updateBalance();
            }
        }
    }

    protected function typeColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'income' => 'success',
                'expense' => 'danger',
                'transfer' => 'info',
                default => 'gray',
            }
        );
    }

    protected function typeIcon(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'income' => 'heroicon-o-arrow-down-circle',
                'expense' => 'heroicon-o-arrow-up-circle',
                'transfer' => 'heroicon-o-arrow-right-circle',
                default => 'heroicon-o-circle-stack',
            }
        );
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                $prefix = match ($this->type) {
                    'income' => '+',
                    'expense' => '-',
                    default => '',
                };

                $currency = $this->account?->currency ?? 'USD';

                return $prefix.$currency.' '.number_format($this->amount, 2);
            }
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipts')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
            ]);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150)
            ->sharpen(10)
            ->nonQueued();
    }
}
