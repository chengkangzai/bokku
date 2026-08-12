<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'icon',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public const DEFAULT_COLORS = [
        '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
        '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
        '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
        '#ec4899', '#f43f5e', '#a16207', '#64748b', '#0d9488',
    ];

    public static function nextDefaultColor(int $userId): string
    {
        $used = static::query()
            ->where('user_id', $userId)
            ->pluck('color')
            ->all();

        $unused = array_values(array_diff(self::DEFAULT_COLORS, $used));

        if ($unused !== []) {
            return $unused[0];
        }

        return self::DEFAULT_COLORS[count($used) % count(self::DEFAULT_COLORS)];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function getMonthlyTotal($month = null, $year = null): float
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $sumInCents = $this->transactions()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        return round($sumInCents / 100, 2);
    }

    protected function defaultIcon(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->type) {
                'income' => 'heroicon-o-arrow-trending-up',
                'expense' => 'heroicon-o-arrow-trending-down',
                default => 'heroicon-o-tag',
            }
        );
    }

    public function getBudgetForUser(int $userId): ?Budget
    {
        return $this->budgets()->where('user_id', $userId)->where('is_active', true)->first();
    }

    public function hasBudget(): bool
    {
        return $this->getBudgetForUser($this->user_id) !== null;
    }

    public function getBudgetStatus(): ?string
    {
        $budget = $this->getBudgetForUser($this->user_id);

        return $budget?->getStatus();
    }

    public function getBudgetProgress(): int
    {
        $budget = $this->getBudgetForUser($this->user_id);

        return $budget?->getProgressPercentage() ?? 0;
    }

    public function getBudgetWarning(float $additionalAmount): ?string
    {
        $budget = $this->getBudgetForUser($this->user_id);

        if (! $budget) {
            return null;
        }

        $currentSpent = $budget->getSpentAmount();
        $totalSpent = $currentSpent + $additionalAmount;
        $budgetAmount = $budget->amount;

        // Handle zero budget amount
        if ($budgetAmount <= 0) {
            return null;
        }

        if ($totalSpent > $budgetAmount) {
            $overage = $totalSpent - $budgetAmount;

            return '⚠️ This will put you MYR '.number_format($overage, 2)." over your {$this->name} budget";
        } elseif (($totalSpent / $budgetAmount) >= 0.8) {
            $percentage = round(($totalSpent / $budgetAmount) * 100);

            return "💡 This will use {$percentage}% of your {$this->name} budget";
        }

        return null;
    }
}
