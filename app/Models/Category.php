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
}
