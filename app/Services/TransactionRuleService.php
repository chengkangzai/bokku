<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TransactionRuleService
{
    public const APPLY_TO = ['all', 'income', 'expense', 'transfer'];

    public const FIELDS = ['description', 'amount', 'category_id', 'account_id', 'date'];

    public const ACTION_TYPES = ['set_category', 'set_account', 'set_notes', 'add_tag'];

    /**
     * Operators supported per condition field.
     *
     * @var array<string, array<int, string>>
     */
    public const OPERATORS = [
        'description' => ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'regex'],
        'amount' => ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'],
        'category_id' => ['equals', 'not_equals'],
        'account_id' => ['equals', 'not_equals'],
        'date' => ['equals', 'not_equals', 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal'],
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $forUpdate = false): array
    {
        $required = $forUpdate ? 'nullable' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'conditions' => [$required, 'array', 'min:1'],
            'conditions.*.field' => ['required', 'string', Rule::in(self::FIELDS)],
            'conditions.*.operator' => ['required', 'string'],
            'conditions.*.value' => ['required'],
            'actions' => [$required, 'array', 'min:1'],
            'actions.*.type' => ['required', 'string', Rule::in(self::ACTION_TYPES)],
            'actions.*.category_id' => ['nullable', 'integer'],
            'actions.*.account_id' => ['nullable', 'integer'],
            'actions.*.notes' => ['nullable', 'string'],
            'actions.*.tag' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'stop_processing' => ['nullable', 'boolean'],
            'apply_to' => ['nullable', 'string', Rule::in(self::APPLY_TO)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function create(User $user, array $data): TransactionRule
    {
        $rule = $this->draft($user, $data);
        $rule->save();

        return $rule;
    }

    /**
     * Build a validated but unsaved rule, for previewing a definition before it exists.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function draft(User $user, array $data): TransactionRule
    {
        return new TransactionRule([
            'user_id' => $user->id,
            'name' => $data['name'] ?? 'Draft rule',
            'description' => $data['description'] ?? null,
            'conditions' => $this->normalizeConditions($user, $data['conditions']),
            'actions' => $this->normalizeActions($user, $data['actions'] ?? []),
            'priority' => $data['priority'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'stop_processing' => $data['stop_processing'] ?? false,
            'apply_to' => $data['apply_to'] ?? 'all',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function update(TransactionRule $rule, array $data): TransactionRule
    {
        foreach (['name', 'description', 'priority', 'is_active', 'stop_processing', 'apply_to'] as $field) {
            if (array_key_exists($field, $data)) {
                $rule->{$field} = $data[$field];
            }
        }

        if (isset($data['conditions'])) {
            $rule->conditions = $this->normalizeConditions($rule->user, $data['conditions']);
        }

        if (isset($data['actions'])) {
            $rule->actions = $this->normalizeActions($rule->user, $data['actions']);
        }

        $rule->save();

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(TransactionRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'apply_to' => $rule->apply_to,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'stop_processing' => $rule->stop_processing,
            'conditions' => $rule->conditions,
            'actions' => $rule->actions,
            'times_applied' => $rule->times_applied,
            'last_applied_at' => $rule->last_applied_at?->toIso8601String(),
        ];
    }

    /**
     * Find transactions the rule currently matches, without changing anything.
     *
     * Conditions are evaluated in PHP, so a window of candidates is scanned and
     * only the matches within it are returned.
     *
     * @return array{scanned: int, matched: int, transactions: array<int, array<string, mixed>>}
     */
    public function preview(TransactionRule $rule, int $limit = 25, int $scanLimit = 500): array
    {
        $candidates = $this->candidateTransactions($rule)->limit($scanLimit)->get();

        $matches = $candidates->filter(fn (Transaction $transaction) => $rule->matches($transaction));

        return [
            'scanned' => $candidates->count(),
            'matched' => $matches->count(),
            'transactions' => $matches->take($limit)
                ->map(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'date' => $transaction->date->toDateString(),
                    'description' => $transaction->description,
                    'amount' => $transaction->amount,
                    'type' => $transaction->type->value,
                    'category_id' => $transaction->category_id,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Builder<Transaction>
     */
    public function candidateTransactions(TransactionRule $rule): Builder
    {
        $query = Transaction::query()
            ->where('user_id', $rule->user_id)
            ->orderBy('date', 'desc');

        if ($rule->apply_to !== 'all') {
            $query->where('type', $rule->apply_to);
        }

        return $query;
    }

    /**
     * @param  array<int, array<string, mixed>>  $conditions
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function normalizeConditions(User $user, array $conditions): array
    {
        return collect($conditions)->map(function (array $condition) use ($user): array {
            $field = $condition['field'];
            $operator = $condition['operator'];

            if (! in_array($operator, self::OPERATORS[$field], true)) {
                $supported = implode(', ', self::OPERATORS[$field]);

                throw new InvalidArgumentException("Operator '{$operator}' is not supported for field '{$field}'. Supported: {$supported}.");
            }

            if ($field === 'amount' && ! is_numeric($condition['value'])) {
                throw new InvalidArgumentException('Amount conditions require a numeric value in decimal format (e.g., 100.50).');
            }

            if ($operator === 'regex') {
                $this->assertValidRegex((string) $condition['value']);
            }

            if ($field === 'category_id') {
                $this->assertOwnsCategory($user, $condition['value']);
            }

            if ($field === 'account_id') {
                $this->assertOwnsAccount($user, $condition['value']);
            }

            return [
                'field' => $field,
                'operator' => $operator,
                'value' => $field === 'amount' ? (float) $condition['value'] : $condition['value'],
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function normalizeActions(User $user, array $actions): array
    {
        return collect($actions)->map(function (array $action) use ($user): array {
            $type = $action['type'];

            return match ($type) {
                'set_category' => [
                    'type' => 'set_category',
                    'category_id' => $this->assertOwnsCategory($user, $action['category_id'] ?? null),
                ],
                'set_account' => [
                    'type' => 'set_account',
                    'account_id' => $this->assertOwnsAccount($user, $action['account_id'] ?? null),
                ],
                'set_notes' => [
                    'type' => 'set_notes',
                    'notes' => $this->assertFilled($action['notes'] ?? null, 'set_notes actions require a notes value.'),
                ],
                'add_tag' => [
                    'type' => 'add_tag',
                    'tag' => $this->assertFilled($action['tag'] ?? null, 'add_tag actions require a tag value.'),
                ],
                default => throw new InvalidArgumentException("Unsupported action type '{$type}'."),
            };
        })->values()->all();
    }

    private function assertValidRegex(string $pattern): void
    {
        if (@preg_match('/'.$pattern.'/i', '') === false) {
            throw new InvalidArgumentException("The regex pattern '{$pattern}' is not valid.");
        }
    }

    private function assertOwnsCategory(User $user, mixed $categoryId): int
    {
        if (! $categoryId || ! Category::where('user_id', $user->id)->whereKey($categoryId)->exists()) {
            throw new InvalidArgumentException('A category_id you own is required.');
        }

        return (int) $categoryId;
    }

    private function assertOwnsAccount(User $user, mixed $accountId): int
    {
        if (! $accountId || ! $user->accounts()->whereKey($accountId)->exists()) {
            throw new InvalidArgumentException('An account_id you own is required.');
        }

        return (int) $accountId;
    }

    private function assertFilled(mixed $value, string $message): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
