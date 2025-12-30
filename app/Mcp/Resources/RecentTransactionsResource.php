<?php

namespace App\Mcp\Resources;

use App\Models\Transaction;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class RecentTransactionsResource extends Resource
{
    protected string $uri = 'bokku://transactions/recent';

    protected string $mimeType = 'application/json';

    protected string $description = 'The 20 most recent transactions for the user, including account and category details.';

    public function handle(Request $request): Response
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->with(['account', 'category', 'fromAccount', 'toAccount'])
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $totalIncome = $transactions->where('type', 'income')->sum('amount');
        $totalExpenses = $transactions->where('type', 'expense')->sum('amount');

        $recentTransactions = [
            'generated_at' => now()->toIso8601String(),
            'transaction_count' => $transactions->count(),
            'summary' => [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'net' => $totalIncome - $totalExpenses,
            ],
            'transactions' => $transactions->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'description' => $transaction->description,
                'date' => $transaction->date->format('Y-m-d'),
                'reconciled' => $transaction->is_reconciled,
                'account' => $transaction->account?->name,
                'category' => $transaction->category?->name,
                'from_account' => $transaction->fromAccount?->name,
                'to_account' => $transaction->toAccount?->name,
            ])->toArray(),
        ];

        return Response::text(json_encode($recentTransactions, JSON_PRETTY_PRINT));
    }
}
