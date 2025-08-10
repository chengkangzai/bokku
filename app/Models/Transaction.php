<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Transaction extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'date',
        'account_id',
        'category_id',
        'from_account_id',
        'to_account_id',
        'reference',
        'notes',
        'is_reconciled',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_reconciled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->updateAccountBalances();
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

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'income' => 'success',
            'expense' => 'danger',
            'transfer' => 'info',
            default => 'gray',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'income' => 'heroicon-o-arrow-down-circle',
            'expense' => 'heroicon-o-arrow-up-circle',
            'transfer' => 'heroicon-o-arrow-right-circle',
            default => 'heroicon-o-circle-stack',
        };
    }

    public function getFormattedAmountAttribute(): string
    {
        $prefix = match ($this->type) {
            'income' => '+',
            'expense' => '-',
            default => '',
        };

        $currency = $this->account?->currency ?? 'USD';

        return $prefix.$currency.' '.number_format($this->amount, 2);
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
            ])
            ->useDisk('public');
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
