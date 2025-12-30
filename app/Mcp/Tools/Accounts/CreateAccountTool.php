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

class CreateAccountTool extends Tool
{
    protected string $description = 'Create a new financial account.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(AccountType::class)],
            'currency' => ['required', 'string', 'size:3'],
            'initial_balance' => ['nullable', 'numeric'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Please provide a name for the account.',
            'type.required' => 'Please specify the account type (bank, cash, credit_card, or loan).',
            'type.*' => 'Invalid account type. Must be one of: bank, cash, credit_card, loan.',
            'currency.required' => 'Please specify the currency code (e.g., USD, EUR).',
        ]);

        $initialBalance = $validated['initial_balance'] ?? 0;

        $account = Account::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => strtoupper($validated['currency']),
            'initial_balance' => $initialBalance,
            'balance' => $initialBalance,
            'account_number' => $validated['account_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return Response::structured([
            'message' => "Account '{$account->name}' created successfully.",
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'balance' => $account->balance,
                'currency' => $account->currency,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The account name')
                ->required(),
            'type' => $schema->string()
                ->enum(['bank', 'cash', 'credit_card', 'loan'])
                ->description('The account type')
                ->required(),
            'currency' => $schema->string()
                ->description('The 3-letter currency code (e.g., USD, EUR, MYR)')
                ->required(),
            'initial_balance' => $schema->number()
                ->description('The initial balance in decimal format (e.g., 1000.50)')
                ->default(0),
            'account_number' => $schema->string()
                ->description('Optional account number for reference'),
            'notes' => $schema->string()
                ->description('Optional notes about the account'),
            'is_active' => $schema->boolean()
                ->description('Whether the account is active')
                ->default(true),
        ];
    }
}
