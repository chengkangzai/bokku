<?php

namespace App\Mcp\Tools\Accounts;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class AdjustBalanceTool extends Tool
{
    protected string $description = 'Adjust an account balance for reconciliation by creating an adjustment transaction.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'account_id' => ['required', 'integer'],
            'new_balance' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'account_id.required' => 'Please specify the account ID.',
            'new_balance.required' => 'Please specify the new balance amount.',
        ]);

        $account = Account::where('user_id', $request->user()->id)
            ->find($validated['account_id']);

        if (! $account) {
            return Response::error('Account not found or access denied.');
        }

        $newBalance = (float) $validated['new_balance'];
        $currentBalance = $account->balance;
        $difference = round($newBalance - $currentBalance, 2);

        if ($difference == 0) {
            return Response::structured([
                'message' => 'No adjustment needed. Balance is already correct.',
                'account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'balance' => $account->balance,
                ],
            ]);
        }

        $type = $difference > 0 ? TransactionType::Income : TransactionType::Expense;
        $amount = abs($difference);

        Transaction::create([
            'user_id' => $request->user()->id,
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'date' => now(),
            'description' => $validated['description'] ?? 'Balance adjustment',
            'is_reconciled' => true,
        ]);

        $account->refresh();

        return Response::structured([
            'message' => "Balance adjusted from {$account->currency} ".number_format($currentBalance, 2)." to {$account->currency} ".number_format($newBalance, 2),
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'balance' => $account->balance,
                'formatted_balance' => $account->formatted_balance,
            ],
            'adjustment' => [
                'type' => $type->value,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()
                ->description('The account ID to adjust')
                ->required(),
            'new_balance' => $schema->number()
                ->description('The new balance amount in decimal format (e.g., 1500.50)')
                ->required(),
            'description' => $schema->string()
                ->description('Optional description for the adjustment transaction')
                ->default('Balance adjustment'),
        ];
    }
}
