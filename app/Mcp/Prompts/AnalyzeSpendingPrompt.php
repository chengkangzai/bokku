<?php

namespace App\Mcp\Prompts;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class AnalyzeSpendingPrompt extends Prompt
{
    protected string $description = 'Analyze spending patterns for the authenticated user over a specified period.';

    /**
     * @return Response|ResponseFactory|array<int, Response>
     */
    public function handle(Request $request): Response|ResponseFactory|array
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'in:month,quarter,year'],
        ], [
            'period.required' => 'Please specify the period to analyze (month, quarter, or year).',
            'period.in' => 'Period must be one of: month, quarter, year.',
        ]);

        $period = $validated['period'];
        $startDate = $this->getStartDate($period);
        $endDate = now();

        $transactions = Transaction::where('user_id', $request->user()->id)
            ->where('type', 'expense')
            ->whereBetween('date', [$startDate, $endDate])
            ->with('category')
            ->get();

        $totalSpending = $transactions->sum('amount');
        $transactionCount = $transactions->count();

        $byCategory = $transactions->groupBy(fn ($t) => $t->category?->name ?? 'Uncategorized')
            ->map(fn ($group) => [
                'total' => $group->sum('amount'),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->take(10);

        $categoryBreakdown = $byCategory->map(function ($data, $category) use ($totalSpending) {
            $percentage = $totalSpending > 0 ? round(($data['total'] / $totalSpending) * 100, 1) : 0;

            return "{$category}: \${$data['total']} ({$percentage}% - {$data['count']} transactions)";
        })->implode("\n");

        $periodLabel = match ($period) {
            'month' => 'the past month',
            'quarter' => 'the past quarter',
            'year' => 'the past year',
        };

        $systemPrompt = <<<'MARKDOWN'
            You are a personal finance analyst helping the user understand their spending patterns.
            Analyze the spending data provided and give actionable insights.
            Be specific about which categories are highest and suggest areas for potential savings.
            Use a supportive, non-judgmental tone.
        MARKDOWN;

        $userPrompt = <<<MARKDOWN
            Please analyze my spending over {$periodLabel}:

            **Summary:**
            - Total Spending: \${$totalSpending}
            - Number of Transactions: {$transactionCount}
            - Period: {$startDate->format('M j, Y')} to {$endDate->format('M j, Y')}

            **Top Categories:**
            {$categoryBreakdown}

            Please provide:
            1. Key observations about my spending patterns
            2. Categories where I might be overspending
            3. Specific suggestions to reduce spending
            4. Any positive trends you notice
        MARKDOWN;

        return [
            Response::text(trim($systemPrompt))->asAssistant(),
            Response::text(trim($userPrompt)),
        ];
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'period',
                description: 'The period to analyze: month, quarter, or year.',
                required: true,
            ),
        ];
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
        };
    }
}
