<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RecurringTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $ahmad = User::where('email', 'ahmad@example.com')->first();

        if ($ahmad) {
            $this->createRecurringTransactionsForAhmad($ahmad);
        }
    }

    private function createRecurringTransactionsForAhmad(User $user): void
    {
        // Get accounts
        $maybank = Account::where('user_id', $user->id)->where('name', 'Maybank Savings')->first();
        $maybankVisa = Account::where('user_id', $user->id)->where('name', 'Maybank Visa')->first();
        $carLoan = Account::where('user_id', $user->id)->where('name', 'Car Loan - Honda City')->first();

        // Get categories
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        $recurringTransactions = [
            // Monthly salary
            [
                'user_id' => $user->id,
                'description' => 'Monthly Salary',
                'type' => 'income',
                'amount' => 4500, // MYR4,500.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Salary']->id ?? null,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 25,
                'start_date' => Carbon::now()->startOfMonth()->setDay(25),
                'next_date' => Carbon::now()->startOfMonth()->setDay(25)->isFuture()
                    ? Carbon::now()->startOfMonth()->setDay(25)
                    : Carbon::now()->addMonth()->startOfMonth()->setDay(25),
                'auto_process' => false,
                'is_active' => true,
            ],

            // Monthly rent
            [
                'user_id' => $user->id,
                'description' => 'Monthly Rent',
                'type' => 'expense',
                'amount' => 1200, // MYR1,200.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Home & Rent']->id ?? null,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 1,
                'start_date' => Carbon::now()->startOfMonth(),
                'next_date' => Carbon::now()->startOfMonth()->isFuture()
                    ? Carbon::now()->startOfMonth()
                    : Carbon::now()->addMonth()->startOfMonth(),
                'auto_process' => false,
                'is_active' => true,
            ],

            // Internet bill
            [
                'user_id' => $user->id,
                'description' => 'Unifi Internet',
                'type' => 'expense',
                'amount' => 99, // MYR99.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Bills & Utilities']->id ?? null,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 15,
                'start_date' => Carbon::now()->startOfMonth()->setDay(15),
                'next_date' => Carbon::now()->startOfMonth()->setDay(15)->isFuture()
                    ? Carbon::now()->startOfMonth()->setDay(15)
                    : Carbon::now()->addMonth()->startOfMonth()->setDay(15),
                'auto_process' => false,
                'is_active' => true,
            ],

            // Car insurance
            [
                'user_id' => $user->id,
                'description' => 'Car Insurance Premium',
                'type' => 'expense',
                'amount' => 150, // MYR150.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Insurance']->id ?? null,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 10,
                'start_date' => Carbon::now()->startOfMonth()->setDay(10),
                'next_date' => Carbon::now()->startOfMonth()->setDay(10)->isFuture()
                    ? Carbon::now()->startOfMonth()->setDay(10)
                    : Carbon::now()->addMonth()->startOfMonth()->setDay(10),
                'auto_process' => false,
                'is_active' => true,
            ],

            // Car loan payment
            [
                'user_id' => $user->id,
                'description' => 'Car Loan Payment',
                'type' => 'expense',
                'amount' => 650, // MYR650.00
                'account_id' => $maybank->id,
                'to_account_id' => $carLoan->id,
                'category_id' => $categories['Loan Payments']->id ?? null,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 5,
                'start_date' => Carbon::now()->startOfMonth()->setDay(5),
                'next_date' => Carbon::now()->startOfMonth()->setDay(5)->isFuture()
                    ? Carbon::now()->startOfMonth()->setDay(5)
                    : Carbon::now()->addMonth()->startOfMonth()->setDay(5),
                'auto_process' => false,
                'is_active' => true,
            ],

            // Netflix subscription
            [
                'user_id' => $user->id,
                'description' => 'Netflix Premium Subscription',
                'type' => 'expense',
                'amount' => 45, // MYR45.00
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Entertainment']->id ?? null,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 12,
                'start_date' => Carbon::now()->startOfMonth()->setDay(12),
                'next_date' => Carbon::now()->startOfMonth()->setDay(12)->isFuture()
                    ? Carbon::now()->startOfMonth()->setDay(12)
                    : Carbon::now()->addMonth()->startOfMonth()->setDay(12),
                'auto_process' => false,
                'is_active' => true,
            ],

            // Weekly groceries budget
            [
                'user_id' => $user->id,
                'description' => 'Weekly Groceries Budget',
                'type' => 'expense',
                'amount' => 100, // MYR100.00
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Groceries']->id ?? null,
                'frequency' => 'weekly',
                'interval' => 1,
                'day_of_week' => 0, // Sunday
                'start_date' => Carbon::now()->startOfWeek(),
                'next_date' => Carbon::now()->startOfWeek()->isFuture()
                    ? Carbon::now()->startOfWeek()
                    : Carbon::now()->addWeek()->startOfWeek(),
                'auto_process' => false,
                'is_active' => true,
            ],
        ];

        foreach ($recurringTransactions as $transaction) {
            RecurringTransaction::firstOrCreate(
                [
                    'user_id' => $transaction['user_id'],
                    'description' => $transaction['description'],
                ],
                $transaction
            );
        }
    }
}
