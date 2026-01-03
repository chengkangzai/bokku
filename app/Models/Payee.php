<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PayeeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'default_category_id',
        'notes',
        'total_amount',
        'is_active',
    ];

    protected $casts = [
        'type' => PayeeType::class,
        'total_amount' => MoneyCast::class,
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function recalculateTotalAmount(): void
    {
        $total = $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');

        $this->updateQuietly(['total_amount' => $total / 100]);
    }
}
