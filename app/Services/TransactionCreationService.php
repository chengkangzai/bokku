<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TransactionCreationService
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}type" => ['required', 'string', Rule::enum(TransactionType::class)],
            "{$prefix}amount" => ['required', 'numeric', 'min:0.01'],
            "{$prefix}description" => ['required', 'string', 'max:255'],
            "{$prefix}date" => ['required', 'date'],
            "{$prefix}account_id" => ['nullable', 'integer'],
            "{$prefix}from_account_id" => ['nullable', 'integer'],
            "{$prefix}to_account_id" => ['nullable', 'integer'],
            "{$prefix}category_id" => ['nullable', 'integer'],
            "{$prefix}payee_id" => ['nullable', 'integer'],
            "{$prefix}reference" => ['nullable', 'string', 'max:255'],
            "{$prefix}notes" => ['nullable', 'string'],
            "{$prefix}is_reconciled" => ['nullable', 'boolean'],
            "{$prefix}tags" => ['nullable', 'array'],
            "{$prefix}tags.*" => ['string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function create(User $user, array $data): Transaction
    {
        $type = TransactionType::from($data['type']);

        $accountIds = $type === TransactionType::Transfer
            ? $this->resolveTransferAccounts($user, $data)
            : $this->resolveSingleAccount($user, $data);

        $this->assertOwnsCategory($user, $data['category_id'] ?? null);
        $this->assertOwnsPayee($user, $data['payee_id'] ?? null);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'date' => $data['date'],
            'category_id' => $data['category_id'] ?? null,
            'payee_id' => $data['payee_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_reconciled' => $data['is_reconciled'] ?? false,
            ...$accountIds,
        ]);

        if (isset($data['tags'])) {
            $transaction->syncUserTags($data['tags']);
        }

        return $transaction;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'formatted_amount' => $transaction->formatted_amount,
            'description' => $transaction->description,
            'date' => $transaction->date->toDateString(),
            'is_reconciled' => $transaction->is_reconciled,
            'tags' => $transaction->getUserTags()->pluck('name')->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{account_id: int, from_account_id: int, to_account_id: int}
     */
    private function resolveTransferAccounts(User $user, array $data): array
    {
        if (! isset($data['from_account_id']) || ! isset($data['to_account_id'])) {
            throw new InvalidArgumentException('Transfer transactions require both from_account_id and to_account_id.');
        }

        $fromAccount = Account::where('user_id', $user->id)->find($data['from_account_id']);
        $toAccount = Account::where('user_id', $user->id)->find($data['to_account_id']);

        if (! $fromAccount || ! $toAccount) {
            throw new InvalidArgumentException('One or both accounts not found or access denied.');
        }

        if ($fromAccount->id === $toAccount->id) {
            throw new InvalidArgumentException('Cannot transfer to the same account.');
        }

        return [
            'account_id' => $fromAccount->id,
            'from_account_id' => $fromAccount->id,
            'to_account_id' => $toAccount->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{account_id: int, from_account_id: null, to_account_id: null}
     */
    private function resolveSingleAccount(User $user, array $data): array
    {
        if (! isset($data['account_id'])) {
            throw new InvalidArgumentException('Income and expense transactions require an account_id.');
        }

        $account = Account::where('user_id', $user->id)->find($data['account_id']);

        if (! $account) {
            throw new InvalidArgumentException('Account not found or access denied.');
        }

        return [
            'account_id' => $account->id,
            'from_account_id' => null,
            'to_account_id' => null,
        ];
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

    private function assertOwnsPayee(User $user, ?int $payeeId): void
    {
        if ($payeeId === null) {
            return;
        }

        if (! Payee::where('user_id', $user->id)->whereKey($payeeId)->exists()) {
            throw new InvalidArgumentException('Payee not found or access denied.');
        }
    }
}
