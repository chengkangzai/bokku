<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (!$user) {
            return;
        }

        $incomeCategories = [
            ['name' => 'Salary', 'color' => '#10b981', 'icon' => 'banknotes'],
            ['name' => 'Freelance', 'color' => '#3b82f6', 'icon' => 'briefcase'],
            ['name' => 'Investment Returns', 'color' => '#8b5cf6', 'icon' => 'chart-bar'],
            ['name' => 'Bonus', 'color' => '#f59e0b', 'icon' => 'gift'],
            ['name' => 'Other Income', 'color' => '#6b7280', 'icon' => 'plus-circle'],
        ];

        $expenseCategories = [
            ['name' => 'Food & Dining', 'color' => '#ef4444', 'icon' => 'shopping-cart'],
            ['name' => 'Transportation', 'color' => '#3b82f6', 'icon' => 'truck'],
            ['name' => 'Shopping', 'color' => '#ec4899', 'icon' => 'shopping-bag'],
            ['name' => 'Entertainment', 'color' => '#8b5cf6', 'icon' => 'film'],
            ['name' => 'Bills & Utilities', 'color' => '#f59e0b', 'icon' => 'lightning-bolt'],
            ['name' => 'Healthcare', 'color' => '#10b981', 'icon' => 'heart'],
            ['name' => 'Education', 'color' => '#6366f1', 'icon' => 'academic-cap'],
            ['name' => 'Insurance', 'color' => '#14b8a6', 'icon' => 'shield-check'],
            ['name' => 'Rent', 'color' => '#f97316', 'icon' => 'home'],
            ['name' => 'Petrol', 'color' => '#64748b', 'icon' => 'fire'],
            ['name' => 'Groceries', 'color' => '#84cc16', 'icon' => 'shopping-cart'],
            ['name' => 'Personal Care', 'color' => '#fb923c', 'icon' => 'sparkles'],
            ['name' => 'Gifts & Donations', 'color' => '#db2777', 'icon' => 'gift'],
            ['name' => 'Travel', 'color' => '#0ea5e9', 'icon' => 'globe-alt'],
            ['name' => 'Other Expenses', 'color' => '#6b7280', 'icon' => 'dots-horizontal'],
        ];

        $sortOrder = 0;

        foreach ($incomeCategories as $category) {
            Category::create([
                'user_id' => $user->id,
                'name' => $category['name'],
                'type' => 'income',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $sortOrder++,
            ]);
        }

        foreach ($expenseCategories as $category) {
            Category::create([
                'user_id' => $user->id,
                'name' => $category['name'],
                'type' => 'expense',
                'icon' => $category['icon'],
                'color' => $category['color'],
                'sort_order' => $sortOrder++,
            ]);
        }
    }
}