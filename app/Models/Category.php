<?php

namespace App\Models;

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

        return $this->transactions()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount') / 100;
    }

    public function getDefaultIconAttribute(): string
    {
        return match ($this->type) {
            'income' => 'heroicon-o-arrow-trending-up',
            'expense' => 'heroicon-o-arrow-trending-down',
            default => 'heroicon-o-tag',
        };
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
        
        if (!$budget) {
            return null;
        }

        $currentSpent = $budget->getSpentAmount();
        $totalSpent = $currentSpent + $additionalAmount;
        $budgetAmount = $budget->amount;

        if ($totalSpent > $budgetAmount) {
            $overage = $totalSpent - $budgetAmount;
            return "⚠️ This will put you RM " . number_format($overage, 2) . " over your {$this->name} budget";
        } elseif (($totalSpent / $budgetAmount) >= 0.8) {
            $percentage = round(($totalSpent / $budgetAmount) * 100);
            return "💡 This will use {$percentage}% of your {$this->name} budget";
        }

        return null;
    }
}
