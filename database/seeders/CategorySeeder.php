<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();

        if ($admin) {
            $this->createCategoriesForUser($admin);
        }
    }

    private function createCategoriesForUser(User $user): void
    {
        $incomeCategories = [
            ['name' => 'Salary', 'color' => '#10b981', 'icon' => 'banknotes'],
            ['name' => 'Freelance', 'color' => '#3b82f6', 'icon' => 'briefcase'],
            ['name' => 'Investment Returns', 'color' => '#8b5cf6', 'icon' => 'chart-bar'],
            ['name' => 'Other Income', 'color' => '#6b7280', 'icon' => 'plus-circle'],
        ];

        $expenseCategories = [
            // Essential Categories
            ['name' => 'Food & Dining', 'color' => '#ef4444', 'icon' => 'cake'],
            ['name' => 'Transportation', 'color' => '#22c55e', 'icon' => 'truck'],
            ['name' => 'Bills & Utilities', 'color' => '#f59e0b', 'icon' => 'bolt'],
            ['name' => 'Groceries', 'color' => '#84cc16', 'icon' => 'shopping-cart'],
            ['name' => 'Shopping', 'color' => '#ec4899', 'icon' => 'shopping-bag'],
            ['name' => 'Entertainment', 'color' => '#8b5cf6', 'icon' => 'film'],
            ['name' => 'Healthcare', 'color' => '#dc2626', 'icon' => 'heart'],
            ['name' => 'Personal Care', 'color' => '#06b6d4', 'icon' => 'sparkles'],
            ['name' => 'Home & Rent', 'color' => '#65a30d', 'icon' => 'home'],
            ['name' => 'Insurance', 'color' => '#0ea5e9', 'icon' => 'shield-check'],
            ['name' => 'Education', 'color' => '#7c3aed', 'icon' => 'academic-cap'],
            ['name' => 'Loan Payments', 'color' => '#dc2626', 'icon' => 'credit-card'],
            ['name' => 'Others', 'color' => '#6b7280', 'icon' => 'ellipsis-horizontal'],
        ];

        // Create income categories
        foreach ($incomeCategories as $category) {
            Category::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $category['name'],
                    'type' => TransactionType::Income->value,
                ],
                [
                    'user_id' => $user->id,
                    'name' => $category['name'],
                    'type' => TransactionType::Income->value,
                    'color' => $category['color'],
                    'icon' => $category['icon'],
                    'sort_order' => 0,
                ]
            );
        }

        // Create expense categories
        foreach ($expenseCategories as $category) {
            Category::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $category['name'],
                    'type' => TransactionType::Expense->value,
                ],
                [
                    'user_id' => $user->id,
                    'name' => $category['name'],
                    'type' => TransactionType::Expense->value,
                    'color' => $category['color'],
                    'icon' => $category['icon'],
                    'sort_order' => 0,
                ]
            );
        }
    }
}
