<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TransactionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'conditions',
        'actions',
        'priority',
        'is_active',
        'stop_processing',
        'apply_to',
        'times_applied',
        'last_applied_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'times_applied' => 'integer',
        'priority' => 'integer',
        'last_applied_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appliedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'applied_rule_id');
    }

    /**
     * Check if the rule matches a transaction
     */
    public function matches(Transaction $transaction): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check if rule applies to this transaction type
        if ($this->apply_to !== 'all' && $this->apply_to !== $transaction->type) {
            return false;
        }

        // All conditions must match
        foreach ($this->conditions as $condition) {
            if (! $this->evaluateCondition($condition, $transaction)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against a transaction
     */
    protected function evaluateCondition(array $condition, Transaction $transaction): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? '';

        $transactionValue = match ($field) {
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'account_id' => $transaction->account_id,
            'category_id' => $transaction->category_id,
            'date' => $transaction->date,
            default => null,
        };

        if ($transactionValue === null) {
            return false;
        }

        return match ($operator) {
            'equals' => $transactionValue == $value,
            'not_equals' => $transactionValue != $value,
            'contains' => Str::contains(strtolower($transactionValue), strtolower($value)),
            'not_contains' => ! Str::contains(strtolower($transactionValue), strtolower($value)),
            'starts_with' => Str::startsWith(strtolower($transactionValue), strtolower($value)),
            'ends_with' => Str::endsWith(strtolower($transactionValue), strtolower($value)),
            'greater_than' => $transactionValue > $value,
            'less_than' => $transactionValue < $value,
            'greater_than_or_equal' => $transactionValue >= $value,
            'less_than_or_equal' => $transactionValue <= $value,
            'regex' => preg_match('/'.$value.'/i', $transactionValue),
            default => false,
        };
    }

    /**
     * Apply the rule's actions to a transaction
     */
    public function apply(Transaction $transaction): void
    {
        if (! $this->matches($transaction)) {
            return;
        }

        // Apply all actions first
        foreach ($this->actions as $action) {
            $this->executeAction($action, $transaction);
        }

        // Mark which rule was applied
        $transaction->applied_rule_id = $this->id;
        $transaction->saveQuietly(); // Use saveQuietly to avoid triggering events

        // Update rule statistics
        $this->increment('times_applied');
        $this->last_applied_at = now();
        $this->save();
    }

    /**
     * Execute a single action on a transaction
     */
    public function executeAction(array $action, Transaction $transaction): void
    {
        $type = $action['type'] ?? '';

        match ($type) {
            'set_category' => $this->setCategoryAction($action, $transaction),
            'set_account' => $this->setAccountAction($action, $transaction),
            'set_notes' => $this->setNotesAction($action, $transaction),
            'add_tag' => $this->addTagAction($action, $transaction),
            default => null,
        };
    }

    protected function setCategoryAction(array $action, Transaction $transaction): void
    {
        $categoryId = $action['category_id'] ?? null;
        if ($categoryId && Category::where('id', $categoryId)->where('user_id', $transaction->user_id)->exists()) {
            $transaction->category_id = $categoryId;
            $transaction->saveQuietly();
        }
    }

    protected function setAccountAction(array $action, Transaction $transaction): void
    {
        $accountId = $action['account_id'] ?? null;
        if ($accountId && Account::where('id', $accountId)->where('user_id', $transaction->user_id)->exists()) {
            $transaction->account_id = $accountId;
            $transaction->saveQuietly();
        }
    }

    protected function setNotesAction(array $action, Transaction $transaction): void
    {
        $notes = $action['notes'] ?? '';
        if ($notes) {
            $transaction->notes = $notes;
            $transaction->saveQuietly();
        }
    }

    protected function addTagAction(array $action, Transaction $transaction): void
    {
        $tag = $action['tag'] ?? '';
        if ($tag) {
            // Use user-scoped tag type
            $transaction->attachTag($tag, 'user_'.$transaction->user_id);
        }
    }

    /**
     * Scope to get active rules ordered by priority
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority', 'desc');
    }

    /**
     * Apply all matching rules to a transaction
     */
    public static function applyRules(Transaction $transaction): void
    {
        $rules = static::where('user_id', $transaction->user_id)
            ->active()
            ->get();

        $firstAppliedRule = null;

        foreach ($rules as $rule) {
            if ($rule->matches($transaction)) {
                // Apply the actions
                foreach ($rule->actions as $action) {
                    $rule->executeAction($action, $transaction);
                }

                // Mark the first (highest priority) rule that was applied
                if (! $firstAppliedRule) {
                    $firstAppliedRule = $rule;
                    $transaction->applied_rule_id = $rule->id;
                    $transaction->saveQuietly();
                }

                // Update rule statistics
                $rule->increment('times_applied');
                $rule->last_applied_at = now();
                $rule->save();

                // Stop processing if this rule says so
                if ($rule->stop_processing) {
                    break;
                }
            }
        }
    }
}
