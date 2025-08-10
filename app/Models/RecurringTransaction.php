<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'account_id',
        'to_account_id',
        'category_id',
        'frequency',
        'interval',
        'day_of_week',
        'day_of_month',
        'month_of_year',
        'next_date',
        'last_processed',
        'start_date',
        'end_date',
        'is_active',
        'auto_process',
        'notes',
    ];

    protected $casts = [
        'amount' => MoneyCast::class,
        'next_date' => 'date',
        'last_processed' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'auto_process' => 'boolean',
        'interval' => 'integer',
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'month_of_year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function generatedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'recurring_transaction_id');
    }

    public function isDue(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return $this->next_date->isToday() || $this->next_date->isPast();
    }

    public function generateTransaction(): ?Transaction
    {
        if (!$this->isDue()) {
            return null;
        }

        $transactionData = [
            'user_id' => $this->user_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'description' => $this->description,
            'date' => $this->next_date,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'recurring_transaction_id' => $this->id,
            'notes' => "Generated from recurring transaction: {$this->description}",
            'is_reconciled' => false,
        ];

        if ($this->type === 'transfer') {
            $transactionData['from_account_id'] = $this->account_id;
            $transactionData['to_account_id'] = $this->to_account_id;
        }

        $transaction = Transaction::create($transactionData);

        // Update last processed and calculate next date
        $this->last_processed = now();
        $this->next_date = $this->calculateNextDate();
        $this->save();

        return $transaction;
    }

    public function calculateNextDate(): Carbon
    {
        $baseDate = ($this->next_date ?? $this->start_date)->copy();
        
        switch ($this->frequency) {
            case 'daily':
                return $baseDate->addDays($this->interval);
                
            case 'weekly':
                $nextDate = $baseDate->addWeeks($this->interval);
                if ($this->day_of_week) {
                    // Convert our 1-7 (Mon-Sun) to Carbon's 0-6 (Sun-Sat)
                    // Our system: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
                    // Carbon: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
                    $carbonDay = $this->day_of_week % 7; // 7 becomes 0 (Sunday)
                    $nextDate->next($carbonDay);
                }
                return $nextDate;
                
            case 'monthly':
                if ($this->day_of_month) {
                    // Use Carbon's addMonthsNoOverflow for proper month addition
                    // This handles end-of-month scenarios correctly
                    if ($this->day_of_month >= 29) {
                        // For days 29-31, use special handling
                        $nextDate = $baseDate->copy();
                        $targetDay = $this->day_of_month;
                        
                        // Add months
                        $nextDate->addMonthsNoOverflow($this->interval);
                        
                        // If target day is 31, always use end of month
                        if ($targetDay == 31) {
                            $nextDate->endOfMonth();
                        } else {
                            // For days 29-30, use the day if it exists, otherwise end of month
                            $lastDay = $nextDate->copy()->endOfMonth()->day;
                            $nextDate->day(min($targetDay, $lastDay));
                        }
                        
                        return $nextDate->startOfDay();
                    } else {
                        // For days 1-28, simple addition works
                        return $baseDate->day($this->day_of_month)->addMonthsNoOverflow($this->interval);
                    }
                } else {
                    return $baseDate->addMonths($this->interval);
                }
                
            case 'annual':
                $nextDate = $baseDate->addYears($this->interval);
                
                if ($this->month_of_year) {
                    $nextDate->month($this->month_of_year);
                    
                    if ($this->day_of_month) {
                        // Handle February 29 for leap years
                        $lastDay = $nextDate->copy()->endOfMonth()->day;
                        $nextDate->day(min($this->day_of_month, $lastDay));
                    }
                }
                
                return $nextDate;
                
            default:
                return $baseDate->addMonth();
        }
    }

    public function skipOnce(): void
    {
        $this->next_date = $this->calculateNextDate();
        $this->save();
    }

    public function pause(): void
    {
        $this->update(['is_active' => false]);
    }

    public function resume(): void
    {
        $this->update(['is_active' => true]);
    }

    public function getFrequencyLabelAttribute(): string
    {
        $label = match ($this->frequency) {
            'daily' => $this->interval === 1 ? 'Daily' : "Every {$this->interval} days",
            'weekly' => $this->interval === 1 ? 'Weekly' : "Every {$this->interval} weeks",
            'monthly' => $this->interval === 1 ? 'Monthly' : "Every {$this->interval} months",
            'annual' => $this->interval === 1 ? 'Annually' : "Every {$this->interval} years",
            default => $this->frequency,
        };

        // Add specific day information
        if ($this->frequency === 'weekly' && $this->day_of_week) {
            // Convert our 1-7 system to day names
            $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
            $dayName = $days[$this->day_of_week] ?? '';
            if ($dayName) {
                $label .= " on {$dayName}";
            }
        } elseif ($this->frequency === 'monthly' && $this->day_of_month) {
            $label .= " on day {$this->day_of_month}";
        } elseif ($this->frequency === 'annual' && $this->month_of_year) {
            $monthName = Carbon::create()->month($this->month_of_year)->format('F');
            $label .= " in {$monthName}";
            if ($this->day_of_month) {
                $label .= " on day {$this->day_of_month}";
            }
        }

        return $label;
    }

    public function getNextOccurrencesAttribute(): array
    {
        $occurrences = [];
        $tempRecurring = clone $this;
        $date = $this->next_date->copy();
        
        for ($i = 0; $i < 5; $i++) {
            if ($this->end_date && $date->greaterThan($this->end_date)) {
                break;
            }
            $occurrences[] = $date->copy();
            
            // Calculate the next date based on the current temp date
            $tempRecurring->next_date = $date;
            $date = $tempRecurring->calculateNextDate();
        }
        
        return $occurrences;
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereDate('next_date', '<=', now())
                  ->orWhereNull('next_date');
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', now());
            });
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->active()
            ->whereBetween('next_date', [now(), now()->addDays($days)])
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', now());
            });
    }
}