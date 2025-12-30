<?php

namespace App\Mcp\Resources;

use App\Models\Account;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class AccountBalancesResource extends Resource
{
    protected string $uri = 'bokku://accounts/balances';

    protected string $mimeType = 'application/json';

    protected string $description = 'Current balances for all user accounts, organized by account type.';

    public function handle(Request $request): Response
    {
        $accounts = Account::where('user_id', $request->user()->id)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $totalBalance = $accounts->sum('balance');

        $balances = [
            'generated_at' => now()->toIso8601String(),
            'total_balance' => $totalBalance,
            'account_count' => $accounts->count(),
            'accounts' => $accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
            ])->toArray(),
            'by_type' => $accounts->groupBy('type')->map(fn ($group, $type) => [
                'type' => $type,
                'count' => $group->count(),
                'total_balance' => $group->sum('balance'),
                'accounts' => $group->pluck('name')->toArray(),
            ])->values()->toArray(),
        ];

        return Response::text(json_encode($balances, JSON_PRETTY_PRINT));
    }
}
