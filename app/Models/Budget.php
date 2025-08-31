<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'period',
        'start_date',
        'is_active',
        'auto_rollover',
    ];

    protected $casts = [
        'amount' => MoneyCast::class,
        'start_date' => 'date',
        'is_active' => 'boolean',
        'auto_rollover' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getCurrentPeriodStart(): Carbon
    {
        $startDate = $this->start_date;
        $now = now();

        return match ($this->period) {
            'weekly' => $startDate->copy()->startOfWeek(),
            'monthly' => $startDate->copy()->startOfMonth(),
            'annual' => $startDate->copy()->startOfYear(),
            default => $startDate->copy()->startOfMonth(),
        };
    }

    public function getCurrentPeriodEnd(): Carbon
    {
        return match ($this->period) {
            'weekly' => $this->getCurrentPeriodStart()->copy()->endOfWeek(),
            'monthly' => $this->getCurrentPeriodStart()->copy()->endOfMonth(),
            'annual' => $this->getCurrentPeriodStart()->copy()->endOfYear(),
            default => $this->getCurrentPeriodStart()->copy()->endOfMonth(),
        };
    }

    public function getSpentAmount(): float
    {
        if (! $this->category) {
            return 0;
        }

        // Sum returns the raw database value in cents
        $spentInCents = $this->category->transactions()
            ->where('user_id', $this->user_id)
            ->where('type', 'expense')
            ->whereBetween('date', [
                $this->getCurrentPeriodStart(),
                $this->getCurrentPeriodEnd(),
            ])
            ->sum('amount');

        // Convert from cents to dollars
        return round($spentInCents / 100, 2);
    }

    public function getRemainingAmount(): float
    {
        return $this->amount - $this->getSpentAmount();
    }

    public function getProgressPercentage(): int
    {
        if ($this->amount <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->getSpentAmount() / $this->amount) * 100));
    }

    public function getStatus(): string
    {
        $percentage = $this->getProgressPercentage();

        if ($percentage >= 100) {
            return 'over';
        } elseif ($percentage >= 80) {
            return 'near';
        } else {
            return 'under';
        }
    }

    public function getStatusColor(): string
    {
        return match ($this->getStatus()) {
            'over' => 'danger',
            'near' => 'warning',
            'under' => 'success',
            default => 'gray',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->getStatus()) {
            'over' => 'heroicon-o-exclamation-triangle',
            'near' => 'heroicon-o-exclamation-circle',
            'under' => 'heroicon-o-check-circle',
            default => 'heroicon-o-minus-circle',
        };
    }

    public function getFormattedSpent(): string
    {
        return 'MYR '.number_format($this->getSpentAmount(), 2);
    }

    public function getFormattedBudget(): string
    {
        return 'MYR '.number_format($this->amount, 2);
    }

    public function getFormattedRemaining(): string
    {
        $remaining = $this->getRemainingAmount();
        $prefix = $remaining < 0 ? '-MYR ' : 'MYR ';

        return $prefix.number_format(abs($remaining), 2);
    }

    public function isOverBudget(): bool
    {
        return $this->getSpentAmount() > $this->amount;
    }

    public function isNearBudget(): bool
    {
        return $this->getProgressPercentage() >= 80 && ! $this->isOverBudget();
    }

}
