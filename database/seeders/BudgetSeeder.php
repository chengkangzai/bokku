<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();

        if ($admin) {
            $this->createBudgetsForUser($admin);
        }
    }

    private function createBudgetsForUser(User $user): void
    {
        // Get categories
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        $currentMonth = Carbon::now()->startOfMonth();

        $budgets = [
            // Food & Dining
            [
                'user_id' => $user->id,
                'category_id' => $categories['Food & Dining']->id ?? null,
                'amount' => 500, // MYR500.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Transportation
            [
                'user_id' => $user->id,
                'category_id' => $categories['Transportation']->id ?? null,
                'amount' => 300, // MYR300.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Shopping
            [
                'user_id' => $user->id,
                'category_id' => $categories['Shopping']->id ?? null,
                'amount' => 250, // MYR250.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Entertainment
            [
                'user_id' => $user->id,
                'category_id' => $categories['Entertainment']->id ?? null,
                'amount' => 150, // MYR150.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Bills & Utilities
            [
                'user_id' => $user->id,
                'category_id' => $categories['Bills & Utilities']->id ?? null,
                'amount' => 250, // MYR250.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Groceries
            [
                'user_id' => $user->id,
                'category_id' => $categories['Groceries']->id ?? null,
                'amount' => 400, // MYR400.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
        ];

        foreach ($budgets as $budget) {
            if ($budget['category_id']) {
                Budget::firstOrCreate(
                    [
                        'user_id' => $budget['user_id'],
                        'category_id' => $budget['category_id'],
                    ],
                    $budget
                );
            }
        }
    }
}
