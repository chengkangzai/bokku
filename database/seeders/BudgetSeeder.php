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
        $ahmad = User::where('email', 'ahmad@example.com')->first();

        if ($ahmad) {
            $this->createBudgetsForAhmad($ahmad);
        }
    }

    private function createBudgetsForAhmad(User $user): void
    {
        // Get categories
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        $currentMonth = Carbon::now()->startOfMonth();

        $budgets = [
            // Food & Dining
            [
                'user_id' => $user->id,
                'category_id' => $categories['Groceries']->id,
                'amount' => 80000, // RM800.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Restaurant']->id,
                'amount' => 40000, // RM400.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Food Delivery']->id,
                'amount' => 20000, // RM200.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Transportation
            [
                'user_id' => $user->id,
                'category_id' => $categories['Petrol']->id,
                'amount' => 40000, // RM400.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Parking']->id,
                'amount' => 10000, // RM100.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Toll']->id,
                'amount' => 15000, // RM150.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Shopping
            [
                'user_id' => $user->id,
                'category_id' => $categories['Clothes & Fashion']->id,
                'amount' => 30000, // RM300.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Online Shopping']->id,
                'amount' => 20000, // RM200.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Personal
            [
                'user_id' => $user->id,
                'category_id' => $categories['Healthcare']->id,
                'amount' => 30000, // RM300.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Personal Care']->id,
                'amount' => 10000, // RM100.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Entertainment
            [
                'user_id' => $user->id,
                'category_id' => $categories['Entertainment']->id,
                'amount' => 25000, // RM250.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'is_active' => true,
                'auto_rollover' => false,
            ],
            // Annual budgets
            [
                'user_id' => $user->id,
                'category_id' => $categories['Travel']->id,
                'amount' => 600000, // RM6,000.00
                'period' => 'annual',
                'start_date' => Carbon::now()->startOfYear(),
                'is_active' => true,
                'auto_rollover' => true,
            ],
            [
                'user_id' => $user->id,
                'category_id' => $categories['Insurance']->id,
                'amount' => 480000, // RM4,800.00
                'period' => 'annual',
                'start_date' => Carbon::now()->startOfYear(),
                'is_active' => true,
                'auto_rollover' => false,
            ],
        ];

        foreach ($budgets as $budget) {
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
