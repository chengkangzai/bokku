<?php

namespace App\Mcp\Prompts;

use App\Models\Category;
use App\Models\Transaction;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class BudgetAdvicePrompt extends Prompt
{
    protected string $description = 'Get personalized budget recommendations based on spending patterns.';

    /**
     * @return Response|ResponseFactory|array<int, Response>
     */
    public function handle(Request $request): Response|ResponseFactory|array
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
        ]);

        $userId = $request->user()->id;
        $categoryId = $validated['category_id'] ?? null;

        if ($categoryId) {
            $category = Category::where('user_id', $userId)->find($categoryId);

            if (! $category) {
                return Response::error('Category not found.');
            }

            return $this->categorySpecificAdvice($request, $category);
        }

        return $this->generalBudgetAdvice($request);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'category_id',
                description: 'Optional category ID to get specific budget advice for that category.',
                required: false,
            ),
        ];
    }

    /**
     * @return array<int, Response>
     */
    private function categorySpecificAdvice(Request $request, Category $category): array
    {
        $userId = $request->user()->id;

        $thisMonth = Transaction::where('user_id', $userId)
            ->where('category_id', $category->id)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $lastMonth = Transaction::where('user_id', $userId)
            ->where('category_id', $category->id)
            ->where('type', 'expense')
            ->whereMonth('date', now()->subMonth()->month)
            ->whereYear('date', now()->subMonth()->year)
            ->sum('amount');

        $threeMonthAvg = Transaction::where('user_id', $userId)
            ->where('category_id', $category->id)
            ->where('type', 'expense')
            ->where('date', '>=', now()->subMonths(3))
            ->sum('amount') / 3;

        $trend = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : 0;

        $trendText = $trend > 0 ? "+{$trend}%" : "{$trend}%";

        $systemPrompt = <<<'MARKDOWN'
            You are a personal finance advisor specializing in budget optimization.
            Provide specific, actionable advice for this spending category.
            Consider seasonal variations and typical spending patterns.
        MARKDOWN;

        $userPrompt = <<<MARKDOWN
            Please provide budget advice for my "{$category->name}" spending:

            **Current Spending:**
            - This Month: \${$thisMonth}
            - Last Month: \${$lastMonth}
            - 3-Month Average: \${$threeMonthAvg}
            - Month-over-Month Change: {$trendText}

            Please provide:
            1. Is my spending in this category reasonable?
            2. A suggested monthly budget for this category
            3. Specific tips to optimize spending here
            4. Warning signs to watch for
        MARKDOWN;

        return [
            Response::text(trim($systemPrompt))->asAssistant(),
            Response::text(trim($userPrompt)),
        ];
    }

    /**
     * @return array<int, Response>
     */
    private function generalBudgetAdvice(Request $request): array
    {
        $userId = $request->user()->id;

        $monthlyIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $monthlyExpenses = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $topCategories = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->with('category')
            ->get()
            ->groupBy(fn ($t) => $t->category?->name ?? 'Uncategorized')
            ->map(fn ($group) => $group->sum('amount'))
            ->sortByDesc(fn ($sum) => $sum)
            ->take(5);

        $categoryList = $topCategories->map(fn ($amount, $name) => "- {$name}: \${$amount}")->implode("\n");

        $savingsRate = $monthlyIncome > 0
            ? round((($monthlyIncome - $monthlyExpenses) / $monthlyIncome) * 100, 1)
            : 0;

        $systemPrompt = <<<'MARKDOWN'
            You are a personal finance advisor helping users create sustainable budgets.
            Use the 50/30/20 rule as a guideline (50% needs, 30% wants, 20% savings).
            Provide practical, achievable recommendations.
        MARKDOWN;

        $userPrompt = <<<MARKDOWN
            Please help me create a budget based on my current finances:

            **This Month's Overview:**
            - Total Income: \${$monthlyIncome}
            - Total Expenses: \${$monthlyExpenses}
            - Net Savings: \${$savingsRate}%

            **Top Spending Categories:**
            {$categoryList}

            Please provide:
            1. An assessment of my current budget health
            2. Recommended budget allocations using the 50/30/20 rule
            3. Categories where I should consider cutting back
            4. A realistic savings goal based on my income
        MARKDOWN;

        return [
            Response::text(trim($systemPrompt))->asAssistant(),
            Response::text(trim($userPrompt)),
        ];
    }
}
