<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->createCategoriesForUser($user);
        }
    }

    private function createCategoriesForUser(User $user): void
    {
        $incomeCategories = [
            ['name' => 'Salary', 'color' => '#10b981', 'icon' => 'banknotes'],
            ['name' => 'Freelance', 'color' => '#3b82f6', 'icon' => 'briefcase'],
            ['name' => 'Investment Returns', 'color' => '#8b5cf6', 'icon' => 'chart-bar'],
            ['name' => 'Bonus', 'color' => '#f59e0b', 'icon' => 'gift'],
            ['name' => 'EPF Withdrawal', 'color' => '#14b8a6', 'icon' => 'document-text'],
            ['name' => 'Rental Income', 'color' => '#ec4899', 'icon' => 'home'],
            ['name' => 'Commission', 'color' => '#6366f1', 'icon' => 'currency-dollar'],
            ['name' => 'Dividends', 'color' => '#84cc16', 'icon' => 'trending-up'],
            ['name' => 'Cashback/Rewards', 'color' => '#f97316', 'icon' => 'sparkles'],
            ['name' => 'Other Income', 'color' => '#6b7280', 'icon' => 'plus-circle'],
        ];

        $expenseCategories = [
            // Food & Dining
            ['name' => 'Groceries', 'color' => '#84cc16', 'icon' => 'shopping-cart'],
            ['name' => 'Restaurant', 'color' => '#ef4444', 'icon' => 'cake'],
            ['name' => 'Food Delivery', 'color' => '#f59e0b', 'icon' => 'truck'],
            ['name' => 'Mamak/Kopitiam', 'color' => '#a855f7', 'icon' => 'chat'],
            
            // Transportation
            ['name' => 'Petrol', 'color' => '#64748b', 'icon' => 'fire'],
            ['name' => 'Grab/E-hailing', 'color' => '#22c55e', 'icon' => 'truck'],
            ['name' => 'Parking', 'color' => '#0ea5e9', 'icon' => 'ticket'],
            ['name' => 'Toll', 'color' => '#7c3aed', 'icon' => 'credit-card'],
            ['name' => 'LRT/MRT', 'color' => '#dc2626', 'icon' => 'globe-alt'],
            ['name' => 'Car Maintenance', 'color' => '#ea580c', 'icon' => 'cog'],
            
            // Bills & Utilities
            ['name' => 'Electricity (TNB)', 'color' => '#fbbf24', 'icon' => 'lightning-bolt'],
            ['name' => 'Water Bill', 'color' => '#60a5fa', 'icon' => 'beaker'],
            ['name' => 'Internet/Unifi', 'color' => '#c084fc', 'icon' => 'wifi'],
            ['name' => 'Mobile Phone', 'color' => '#fb923c', 'icon' => 'phone'],
            ['name' => 'Astro/Streaming', 'color' => '#e11d48', 'icon' => 'film'],
            
            // Shopping
            ['name' => 'Clothes & Fashion', 'color' => '#ec4899', 'icon' => 'shopping-bag'],
            ['name' => 'Electronics', 'color' => '#3b82f6', 'icon' => 'desktop-computer'],
            ['name' => 'Online Shopping', 'color' => '#8b5cf6', 'icon' => 'globe'],
            ['name' => 'Household Items', 'color' => '#10b981', 'icon' => 'home'],
            
            // Personal
            ['name' => 'Healthcare', 'color' => '#10b981', 'icon' => 'heart'],
            ['name' => 'Pharmacy', 'color' => '#06b6d4', 'icon' => 'beaker'],
            ['name' => 'Personal Care', 'color' => '#fb923c', 'icon' => 'sparkles'],
            ['name' => 'Haircut/Salon', 'color' => '#f472b6', 'icon' => 'scissors'],
            ['name' => 'Gym/Fitness', 'color' => '#4ade80', 'icon' => 'lightning-bolt'],
            
            // Financial
            ['name' => 'Insurance', 'color' => '#14b8a6', 'icon' => 'shield-check'],
            ['name' => 'Loan Payment', 'color' => '#dc2626', 'icon' => 'document-text'],
            ['name' => 'Credit Card Payment', 'color' => '#991b1b', 'icon' => 'credit-card'],
            ['name' => 'Investment', 'color' => '#059669', 'icon' => 'trending-up'],
            ['name' => 'EPF/SOCSO', 'color' => '#1e40af', 'icon' => 'library'],
            ['name' => 'Zakat', 'color' => '#7c2d12', 'icon' => 'hand'],
            
            // Housing
            ['name' => 'Rent', 'color' => '#f97316', 'icon' => 'home'],
            ['name' => 'Home Maintenance', 'color' => '#0891b2', 'icon' => 'wrench'],
            ['name' => 'Management Fee', 'color' => '#7e22ce', 'icon' => 'office-building'],
            
            // Entertainment & Leisure
            ['name' => 'Entertainment', 'color' => '#8b5cf6', 'icon' => 'film'],
            ['name' => 'Travel', 'color' => '#0ea5e9', 'icon' => 'globe-alt'],
            ['name' => 'Hobbies', 'color' => '#d946ef', 'icon' => 'puzzle'],
            
            // Family & Social
            ['name' => 'Children/Education', 'color' => '#6366f1', 'icon' => 'academic-cap'],
            ['name' => 'Gifts & Donations', 'color' => '#db2777', 'icon' => 'gift'],
            ['name' => 'Wedding/Events', 'color' => '#be123c', 'icon' => 'heart'],
            ['name' => 'Parents/Family', 'color' => '#0f766e', 'icon' => 'users'],
            
            // Others
            ['name' => 'Pet Care', 'color' => '#a16207', 'icon' => 'heart'],
            ['name' => 'Fines/Summons', 'color' => '#b91c1c', 'icon' => 'exclamation'],
            ['name' => 'Miscellaneous', 'color' => '#6b7280', 'icon' => 'dots-horizontal'],
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