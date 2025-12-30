<?php

namespace App\Mcp\Tools\Accounts;

use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetAccountTool extends Tool
{
    protected string $description = 'Get details of a specific account by ID.';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'Please specify the account ID.',
        ]);

        $account = Account::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $account) {
            return Response::error('Account not found or access denied.');
        }

        return Response::structured([
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'balance' => $account->balance,
                'formatted_balance' => $account->formatted_balance,
                'initial_balance' => $account->initial_balance,
                'currency' => $account->currency,
                'account_number' => $account->account_number,
                'notes' => $account->notes,
                'is_active' => $account->is_active,
                'created_at' => $account->created_at->toIso8601String(),
                'updated_at' => $account->updated_at->toIso8601String(),
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
                ->description('The account ID')
                ->required(),
        ];
    }
}
