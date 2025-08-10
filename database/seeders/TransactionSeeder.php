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
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            return;
        }

        $maybankAccount = Account::where('user_id', $user->id)->where('name', 'Maybank Savings')->first();
        $cimbAccount = Account::where('user_id', $user->id)->where('name', 'CIMB Current')->first();
        $cashAccount = Account::where('user_id', $user->id)->where('name', 'Wallet Cash')->first();
        $creditCard = Account::where('user_id', $user->id)->where('name', 'Visa Credit Card')->first();

        $salaryCategory = Category::where('user_id', $user->id)->where('name', 'Salary')->first();
        $foodCategory = Category::where('user_id', $user->id)->where('name', 'Food & Dining')->first();
        $transportCategory = Category::where('user_id', $user->id)->where('name', 'Transportation')->first();
        $shoppingCategory = Category::where('user_id', $user->id)->where('name', 'Shopping')->first();
        $billsCategory = Category::where('user_id', $user->id)->where('name', 'Bills & Utilities')->first();
        $petrolCategory = Category::where('user_id', $user->id)->where('name', 'Petrol')->first();
        $groceriesCategory = Category::where('user_id', $user->id)->where('name', 'Groceries')->first();
        $entertainmentCategory = Category::where('user_id', $user->id)->where('name', 'Entertainment')->first();

        $transactions = [
            // Current month transactions
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 6500.00,
                'description' => 'Monthly Salary',
                'date' => Carbon::now()->startOfMonth(),
                'account_id' => $maybankAccount->id,
                'category_id' => $salaryCategory->id,
                'is_reconciled' => true,
            ],
            [
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount' => 2000.00,
                'description' => 'Transfer to daily account',
                'date' => Carbon::now()->startOfMonth()->addDays(1),
                'account_id' => $cimbAccount->id,
                'from_account_id' => $maybankAccount->id,
                'to_account_id' => $cimbAccount->id,
                'is_reconciled' => true,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 150.00,
                'description' => 'Lunch at Restaurant',
                'date' => Carbon::now()->subDays(7),
                'account_id' => $creditCard->id,
                'category_id' => $foodCategory->id,
                'is_reconciled' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 85.00,
                'description' => 'Grab rides',
                'date' => Carbon::now()->subDays(6),
                'account_id' => $cimbAccount->id,
                'category_id' => $transportCategory->id,
                'is_reconciled' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 320.00,
                'description' => 'Groceries at Lotus',
                'date' => Carbon::now()->subDays(5),
                'account_id' => $creditCard->id,
                'category_id' => $groceriesCategory->id,
                'is_reconciled' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 180.00,
                'description' => 'Petrol Ron95',
                'date' => Carbon::now()->subDays(4),
                'account_id' => $creditCard->id,
                'category_id' => $petrolCategory->id,
                'is_reconciled' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 450.00,
                'description' => 'Electricity Bill',
                'date' => Carbon::now()->subDays(3),
                'account_id' => $cimbAccount->id,
                'category_id' => $billsCategory->id,
                'reference' => 'TNB-202501',
                'is_reconciled' => true,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 120.00,
                'description' => 'Internet Bill - Unifi',
                'date' => Carbon::now()->subDays(3),
                'account_id' => $cimbAccount->id,
                'category_id' => $billsCategory->id,
                'reference' => 'TM-202501',
                'is_reconciled' => true,
            ],
            [
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount' => 300.00,
                'description' => 'ATM Withdrawal',
                'date' => Carbon::now()->subDays(2),
                'account_id' => $cashAccount->id,
                'from_account_id' => $cimbAccount->id,
                'to_account_id' => $cashAccount->id,
                'is_reconciled' => true,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 45.00,
                'description' => 'Breakfast at mamak',
                'date' => Carbon::now()->subDays(1),
                'account_id' => $cashAccount->id,
                'category_id' => $foodCategory->id,
                'is_reconciled' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 75.00,
                'description' => 'Movie tickets',
                'date' => Carbon::now()->subDays(1),
                'account_id' => $creditCard->id,
                'category_id' => $entertainmentCategory->id,
                'is_reconciled' => false,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 250.00,
                'description' => 'Clothes shopping at Uniqlo',
                'date' => Carbon::now(),
                'account_id' => $creditCard->id,
                'category_id' => $shoppingCategory->id,
                'is_reconciled' => false,
            ],
            // Previous month transactions for comparison
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 6500.00,
                'description' => 'Monthly Salary',
                'date' => Carbon::now()->subMonth()->startOfMonth(),
                'account_id' => $maybankAccount->id,
                'category_id' => $salaryCategory->id,
                'is_reconciled' => true,
            ],
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 420.00,
                'description' => 'Electricity Bill',
                'date' => Carbon::now()->subMonth()->startOfMonth()->addDays(5),
                'account_id' => $cimbAccount->id,
                'category_id' => $billsCategory->id,
                'reference' => 'TNB-202412',
                'is_reconciled' => true,
            ],
        ];

        foreach ($transactions as $transaction) {
            Transaction::create($transaction);
        }
    }
}
