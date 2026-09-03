<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class RecurringTransactionService
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'annual'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $forUpdate = false): array
    {
        $required = $forUpdate ? 'nullable' : 'required';

        return [
            'type' => [$required, 'string', Rule::enum(TransactionType::class)],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'description' => [$required, 'string', 'max:255'],
            'frequency' => [$required, 'string', Rule::in(self::FREQUENCIES)],
            'account_id' => ['nullable', 'integer'],
            'from_account_id' => ['nullable', 'integer'],
            'to_account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'month_of_year' => ['nullable', 'integer', 'between:1,12'],
            'start_date' => ['nullable', 'date'],
            'next_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'auto_process' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function create(User $user, array $data): RecurringTransaction
    {
        $type = TransactionType::from($data['type']);
        $accounts = $this->resolveAccounts($user, $type, $data);
        $this->assertOwnsCategory($user, $data['category_id'] ?? null);

        $startDate = $data['start_date'] ?? now()->toDateString();
        $this->assertValidDateRange($startDate, $data['end_date'] ?? null);

        return RecurringTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'category_id' => $data['category_id'] ?? null,
            'frequency' => $data['frequency'],
            'interval' => $data['interval'] ?? 1,
            'day_of_week' => $data['day_of_week'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'month_of_year' => $data['month_of_year'] ?? null,
            'start_date' => $startDate,
            'next_date' => $data['next_date'] ?? $startDate,
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'auto_process' => $data['auto_process'] ?? true,
            'notes' => $data['notes'] ?? null,
            ...$accounts,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function update(RecurringTransaction $recurring, array $data): RecurringTransaction
    {
        $user = $recurring->user;
        $originalType = $recurring->type;
        $type = isset($data['type']) ? TransactionType::from($data['type']) : $originalType;

        foreach (['amount', 'description', 'frequency', 'interval', 'day_of_week', 'day_of_month', 'month_of_year', 'start_date', 'next_date', 'end_date', 'is_active', 'auto_process', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $recurring->{$field} = $data[$field];
            }
        }

        if (array_key_exists('category_id', $data)) {
            $this->assertOwnsCategory($user, $data['category_id']);
            $recurring->category_id = $data['category_id'];
        }

        $recurring->type = $type;

        $touchesAccounts = isset($data['account_id']) || isset($data['from_account_id']) || isset($data['to_account_id']);

        if ($touchesAccounts || $type !== $originalType) {
            $accounts = $this->resolveAccounts($user, $type, [
                'account_id' => $data['account_id'] ?? $data['from_account_id'] ?? $recurring->account_id,
                'to_account_id' => $data['to_account_id'] ?? $recurring->to_account_id,
            ]);

            $recurring->fill($accounts);
        }

        $this->assertValidDateRange($recurring->start_date, $recurring->end_date);

        $recurring->save();

        return $recurring;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(RecurringTransaction $recurring): array
    {
        return [
            'id' => $recurring->id,
            'type' => $recurring->type->value,
            'amount' => $recurring->amount,
            'description' => $recurring->description,
            'account' => $this->describeAccount($recurring->account),
            'to_account' => $this->describeAccount($recurring->toAccount),
            'category' => $recurring->category ? [
                'id' => $recurring->category->id,
                'name' => $recurring->category->name,
            ] : null,
            'frequency' => $recurring->frequency,
            'frequency_label' => $recurring->frequency_label,
            'interval' => $recurring->interval,
            'next_date' => $recurring->next_date?->toDateString(),
            'end_date' => $recurring->end_date?->toDateString(),
            'is_active' => $recurring->is_active,
            'auto_process' => $recurring->auto_process,
            'is_due' => $recurring->isDue(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(RecurringTransaction $recurring): array
    {
        return [
            ...$this->summarize($recurring),
            'day_of_week' => $recurring->day_of_week,
            'day_of_month' => $recurring->day_of_month,
            'month_of_year' => $recurring->month_of_year,
            'start_date' => $recurring->start_date?->toDateString(),
            'last_processed' => $recurring->last_processed?->toIso8601String(),
            'notes' => $recurring->notes,
            'next_occurrences' => collect($recurring->next_occurrences)
                ->map(fn ($date) => $date->toDateString())
                ->toArray(),
            'generated_transactions_count' => $recurring->generatedTransactions()->count(),
            'created_at' => $recurring->created_at->toIso8601String(),
            'updated_at' => $recurring->updated_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{account_id: int, to_account_id: ?int}
     *
     * @throws InvalidArgumentException
     */
    private function resolveAccounts(User $user, TransactionType $type, array $data): array
    {
        $sourceId = $data['account_id'] ?? $data['from_account_id'] ?? null;

        if ($sourceId === null) {
            throw new InvalidArgumentException('An account_id is required (use from_account_id for transfers).');
        }

        $source = Account::where('user_id', $user->id)->find($sourceId);

        if (! $source) {
            throw new InvalidArgumentException('Account not found or access denied.');
        }

        if ($type !== TransactionType::Transfer) {
            return ['account_id' => $source->id, 'to_account_id' => null];
        }

        if (! isset($data['to_account_id'])) {
            throw new InvalidArgumentException('Transfer recurring transactions require a to_account_id.');
        }

        $destination = Account::where('user_id', $user->id)->find($data['to_account_id']);

        if (! $destination) {
            throw new InvalidArgumentException('Destination account not found or access denied.');
        }

        if ($destination->id === $source->id) {
            throw new InvalidArgumentException('Cannot transfer to the same account.');
        }

        return ['account_id' => $source->id, 'to_account_id' => $destination->id];
    }

    private function assertValidDateRange(mixed $startDate, mixed $endDate): void
    {
        if (! $startDate || ! $endDate) {
            return;
        }

        if (Carbon::parse($endDate)->lessThan(Carbon::parse($startDate))) {
            throw new InvalidArgumentException('The end_date must be on or after the start_date.');
        }
    }

    private function assertOwnsCategory(User $user, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if (! Category::where('user_id', $user->id)->whereKey($categoryId)->exists()) {
            throw new InvalidArgumentException('Category not found or access denied.');
        }
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function describeAccount(?Account $account): ?array
    {
        if (! $account) {
            return null;
        }

        return ['id' => $account->id, 'name' => $account->name];
    }
}
