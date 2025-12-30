<?php

namespace App\Mcp\Tools\Accounts;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class UpdateAccountTool extends Tool
{
    protected string $description = 'Update an existing account.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::enum(AccountType::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'id.required' => 'Please specify the account ID to update.',
        ]);

        $account = Account::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $account) {
            return Response::error('Account not found or access denied.');
        }

        $updates = [];

        if (isset($validated['name'])) {
            $updates['name'] = $validated['name'];
        }

        if (isset($validated['type'])) {
            $updates['type'] = $validated['type'];
        }

        if (isset($validated['currency'])) {
            $updates['currency'] = strtoupper($validated['currency']);
        }

        if (isset($validated['account_number'])) {
            $updates['account_number'] = $validated['account_number'];
        }

        if (isset($validated['notes'])) {
            $updates['notes'] = $validated['notes'];
        }

        if (isset($validated['is_active'])) {
            $updates['is_active'] = $validated['is_active'];
        }

        $account->update($updates);

        return Response::structured([
            'message' => "Account '{$account->name}' updated successfully.",
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The account ID to update')
                ->required(),
            'name' => $schema->string()
                ->description('The new account name'),
            'type' => $schema->string()
                ->enum(['bank', 'cash', 'credit_card', 'loan'])
                ->description('The new account type'),
            'currency' => $schema->string()
                ->description('The new 3-letter currency code'),
            'account_number' => $schema->string()
                ->description('The new account number'),
            'notes' => $schema->string()
                ->description('New notes about the account'),
            'is_active' => $schema->boolean()
                ->description('Whether the account is active'),
        ];
    }
}
