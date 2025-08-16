<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $ahmad = User::where('email', 'ahmad@example.com')->first();

        if ($ahmad) {
            $this->createTransactionsForAhmad($ahmad);
        }
    }

    private function createTransactionsForAhmad(User $user): void
    {
        // Get accounts
        $maybank = Account::where('user_id', $user->id)->where('name', 'Maybank Savings')->first();
        $tng = Account::where('user_id', $user->id)->where('name', 'Touch n Go eWallet')->first();
        $maybankVisa = Account::where('user_id', $user->id)->where('name', 'Maybank Visa')->first();

        // Get categories
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        // Recent 2 months of transactions
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $transactions = [
            // Current Month - Income
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 4500, // MYR4,500.00
                'description' => 'Monthly Salary',
                'date' => $currentMonth->copy(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Salary']->id ?? null,
                'reference' => 'SAL-' . $currentMonth->format('Ym'),
                'is_reconciled' => true,
                'tags' => ['monthly', 'salary'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 800, // MYR800.00
                'description' => 'Freelance Web Design Project',
                'date' => $currentMonth->copy()->addDays(5),
                'account_id' => $maybank->id,
                'category_id' => $categories['Freelance']->id ?? null,
                'reference' => 'FRL-001',
                'is_reconciled' => true,
                'tags' => ['freelance', 'web-design'],
            ],

            // Current Month - Expenses
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 1200, // MYR1,200.00
                'description' => 'Monthly Rent',
                'date' => $currentMonth->copy()->addDay(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Home & Rent']->id ?? null,
                'reference' => 'RENT-' . $currentMonth->format('Ym'),
                'is_reconciled' => true,
                'tags' => ['monthly', 'rent'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 65, // MYR65.00
                'description' => 'Tesco Groceries',
                'date' => $currentMonth->copy()->addDays(3),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Groceries']->id ?? null,
                'reference' => 'TESCO-001',
                'is_reconciled' => false,
                'tags' => ['groceries', 'tesco'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 18.50, // MYR18.50
                'description' => 'Grab to KLCC',
                'date' => $currentMonth->copy()->addDays(4),
                'account_id' => $tng->id,
                'category_id' => $categories['Transportation']->id ?? null,
                'reference' => 'GRAB-123456',
                'is_reconciled' => true,
                'tags' => ['transport', 'grab'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 45, // MYR45.00
                'description' => 'Netflix Subscription',
                'date' => $currentMonth->copy()->addDays(7),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Entertainment']->id ?? null,
                'reference' => 'NETFLIX-202501',
                'is_reconciled' => true,
                'tags' => ['subscription', 'entertainment'],
            ],

            // Last Month - Examples
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 4500, // MYR4,500.00
                'description' => 'Monthly Salary',
                'date' => $lastMonth->copy(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Salary']->id ?? null,
                'reference' => 'SAL-' . $lastMonth->format('Ym'),
                'is_reconciled' => true,
                'tags' => ['monthly', 'salary'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 1200, // MYR1,200.00
                'description' => 'Monthly Rent',
                'date' => $lastMonth->copy()->addDay(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Home & Rent']->id ?? null,
                'reference' => 'RENT-' . $lastMonth->format('Ym'),
                'is_reconciled' => true,
                'tags' => ['monthly', 'rent'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 165, // MYR165.00
                'description' => 'TNB Electricity Bill',
                'date' => $lastMonth->copy()->addDays(5),
                'account_id' => $maybank->id,
                'category_id' => $categories['Bills & Utilities']->id ?? null,
                'reference' => 'TNB-' . $lastMonth->format('Ym'),
                'is_reconciled' => true,
                'tags' => ['monthly', 'utilities', 'tnb'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 45, // MYR45.00
                'description' => 'Dinner at The Olive',
                'date' => $lastMonth->copy()->addDays(15),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Food & Dining']->id ?? null,
                'reference' => 'OLIVE-001',
                'is_reconciled' => true,
                'tags' => ['restaurant', 'dinner'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 60, // MYR60.00
                'description' => 'Petrol at Petronas',
                'date' => $lastMonth->copy()->addDays(20),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Transportation']->id ?? null,
                'reference' => 'PETRONAS-001',
                'is_reconciled' => true,
                'tags' => ['petrol', 'petronas'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 150, // MYR150.00
                'description' => 'Shopee Online Shopping',
                'date' => $lastMonth->copy()->addDays(25),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Shopping']->id ?? null,
                'reference' => 'SHOPEE-001',
                'is_reconciled' => true,
                'tags' => ['online', 'shopee'],
            ],
        ];

        foreach ($transactions as $transaction) {
            Transaction::firstOrCreate(
                [
                    'user_id' => $transaction['user_id'],
                    'reference' => $transaction['reference'],
                ],
                $transaction
            );
        }
    }
}