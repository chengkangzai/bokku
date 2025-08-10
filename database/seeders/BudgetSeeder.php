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
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $budgets = [
            // Current month budgets
            [
                'user_id' => $user->id,
                'name' => 'Food & Dining Budget',
                'amount' => 150000, // RM1,500.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Groceries']->id,
                    $categories['Restaurant']->id,
                    $categories['Food Delivery']->id,
                    $categories['Mamak/Kopitiam']->id,
                ],
                'alert_threshold' => 80,
                'is_active' => true,
                'notes' => 'Monthly budget for all food-related expenses',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Transportation Budget',
                'amount' => 80000, // RM800.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Petrol']->id,
                    $categories['Grab/E-hailing']->id,
                    $categories['Parking']->id,
                    $categories['Toll']->id,
                    $categories['LRT/MRT']->id,
                ],
                'alert_threshold' => 75,
                'is_active' => true,
                'notes' => 'Transportation and commute expenses',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Utilities Budget',
                'amount' => 60000, // RM600.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Electricity (TNB)']->id,
                    $categories['Water Bill']->id,
                    $categories['Internet/Unifi']->id,
                    $categories['Mobile Phone']->id,
                ],
                'alert_threshold' => 90,
                'is_active' => true,
                'notes' => 'Monthly utilities and bills',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Entertainment Budget',
                'amount' => 40000, // RM400.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Entertainment']->id,
                    $categories['Astro/Streaming']->id,
                    $categories['Hobbies']->id,
                ],
                'alert_threshold' => 70,
                'is_active' => true,
                'notes' => 'Entertainment and leisure activities',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Shopping Budget',
                'amount' => 50000, // RM500.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Clothes & Fashion']->id,
                    $categories['Online Shopping']->id,
                    $categories['Electronics']->id,
                ],
                'alert_threshold' => 80,
                'is_active' => true,
                'notes' => 'Discretionary shopping budget',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Healthcare Budget',
                'amount' => 30000, // RM300.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Healthcare']->id,
                    $categories['Pharmacy']->id,
                    $categories['Personal Care']->id,
                ],
                'alert_threshold' => 85,
                'is_active' => true,
                'notes' => 'Health and personal care expenses',
            ],
            // Quarterly budget
            [
                'user_id' => $user->id,
                'name' => 'Q1 2025 Travel Budget',
                'amount' => 500000, // RM5,000.00
                'period' => 'custom',
                'start_date' => Carbon::create(2025, 1, 1),
                'end_date' => Carbon::create(2025, 3, 31),
                'category_ids' => [
                    $categories['Travel']->id,
                ],
                'alert_threshold' => 90,
                'is_active' => true,
                'notes' => 'Q1 travel budget for Japan trip',
            ],
            // Annual budget
            [
                'user_id' => $user->id,
                'name' => '2025 Investment Target',
                'amount' => 1800000, // RM18,000.00
                'period' => 'yearly',
                'start_date' => Carbon::create(2025, 1, 1),
                'end_date' => Carbon::create(2025, 12, 31),
                'category_ids' => [
                    $categories['Investment']->id,
                ],
                'alert_threshold' => 95,
                'is_active' => true,
                'notes' => 'Annual investment goal - RM1,500/month average',
            ],
            // Last month budget (completed)
            [
                'user_id' => $user->id,
                'name' => 'December 2024 Food Budget',
                'amount' => 150000, // RM1,500.00
                'period' => 'monthly',
                'start_date' => $lastMonth,
                'end_date' => $lastMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Groceries']->id,
                    $categories['Restaurant']->id,
                    $categories['Food Delivery']->id,
                    $categories['Mamak/Kopitiam']->id,
                ],
                'alert_threshold' => 80,
                'is_active' => false,
                'notes' => 'December food budget - exceeded due to year-end celebrations',
            ],
            // Weekly budget
            [
                'user_id' => $user->id,
                'name' => 'Weekly Pocket Money',
                'amount' => 20000, // RM200.00
                'period' => 'weekly',
                'start_date' => Carbon::now()->startOfWeek(),
                'end_date' => Carbon::now()->endOfWeek(),
                'category_ids' => [
                    $categories['Miscellaneous']->id,
                ],
                'alert_threshold' => 70,
                'is_active' => true,
                'notes' => 'Weekly discretionary spending',
            ],
            // Car-related budget
            [
                'user_id' => $user->id,
                'name' => 'Car Maintenance Fund',
                'amount' => 200000, // RM2,000.00
                'period' => 'yearly',
                'start_date' => Carbon::create(2025, 1, 1),
                'end_date' => Carbon::create(2025, 12, 31),
                'category_ids' => [
                    $categories['Car Maintenance']->id,
                ],
                'alert_threshold' => 80,
                'is_active' => true,
                'notes' => 'Annual car service and maintenance budget',
            ],
            // Family support budget
            [
                'user_id' => $user->id,
                'name' => 'Family Support',
                'amount' => 100000, // RM1,000.00
                'period' => 'monthly',
                'start_date' => $currentMonth,
                'end_date' => $currentMonth->copy()->endOfMonth(),
                'category_ids' => [
                    $categories['Parents/Family']->id,
                    $categories['Gifts & Donations']->id,
                ],
                'alert_threshold' => 100, // No alert - fixed commitment
                'is_active' => true,
                'notes' => 'Monthly family support and gifts',
            ],
            // Inactive budget example
            [
                'user_id' => $user->id,
                'name' => 'Wedding Savings (Completed)',
                'amount' => 2000000, // RM20,000.00
                'period' => 'custom',
                'start_date' => Carbon::create(2024, 1, 1),
                'end_date' => Carbon::create(2024, 12, 31),
                'category_ids' => [
                    $categories['Wedding/Events']->id,
                ],
                'alert_threshold' => 90,
                'is_active' => false,
                'notes' => 'Wedding budget for 2024 - successfully completed',
            ],
        ];

        foreach ($budgets as $budget) {
            Budget::create($budget);
        }
    }
}
