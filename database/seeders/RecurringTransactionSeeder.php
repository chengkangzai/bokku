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
        $cimb = Account::where('user_id', $user->id)->where('name', 'CIMB Current')->first();
        $maybankVisa = Account::where('user_id', $user->id)->where('name', 'Maybank Visa')->first();
        $carLoan = Account::where('user_id', $user->id)->where('name', 'Car Loan - Honda City')->first();
        $asbLoan = Account::where('user_id', $user->id)->where('name', 'ASB Loan')->first();
        $stashaway = Account::where('user_id', $user->id)->where('name', 'StashAway Investment')->first();

        // Get categories
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        $recurringTransactions = [
            // Monthly salary
            [
                'user_id' => $user->id,
                'name' => 'Monthly Salary',
                'type' => 'income',
                'amount' => 850000, // RM8,500.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Salary']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 25,
                'start_date' => Carbon::now()->startOfMonth()->setDay(25),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(25)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(25) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(25),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Monthly salary from Tech Solutions Sdn Bhd',
                'tags' => ['salary', 'income', 'monthly'],
            ],
            // Car loan payment
            [
                'user_id' => $user->id,
                'name' => 'Car Loan - Honda City',
                'type' => 'transfer',
                'amount' => 145000, // RM1,450.00
                'account_id' => $carLoan->id,
                'from_account_id' => $maybank->id,
                'to_account_id' => $carLoan->id,
                'category_id' => $categories['Loan Payment']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 5,
                'start_date' => Carbon::now()->startOfMonth()->setDay(5),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(5)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(5) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(5),
                'end_date' => Carbon::now()->addYears(5), // 5 years remaining
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Auto debit for car loan',
                'tags' => ['car-loan', 'monthly', 'auto-debit'],
            ],
            // ASB loan payment
            [
                'user_id' => $user->id,
                'name' => 'ASB Loan Payment',
                'type' => 'transfer',
                'amount' => 35000, // RM350.00
                'account_id' => $asbLoan->id,
                'from_account_id' => $maybank->id,
                'to_account_id' => $asbLoan->id,
                'category_id' => $categories['Loan Payment']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 10,
                'start_date' => Carbon::now()->startOfMonth()->setDay(10),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(10)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(10) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(10),
                'end_date' => Carbon::now()->addYears(10),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'ASB financing monthly payment',
                'tags' => ['asb', 'investment', 'loan'],
            ],
            // TNB electricity bill
            [
                'user_id' => $user->id,
                'name' => 'TNB Electricity Bill',
                'type' => 'expense',
                'amount' => 30000, // RM300.00 (average)
                'account_id' => $cimb->id,
                'category_id' => $categories['Electricity (TNB)']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 7,
                'start_date' => Carbon::now()->startOfMonth()->setDay(7),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(7)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(7) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(7),
                'is_active' => true,
                'auto_create' => false,
                'reminder_days_before' => 3,
                'notes' => 'Average monthly electricity bill',
                'tags' => ['utilities', 'tnb', 'monthly'],
            ],
            // Unifi internet
            [
                'user_id' => $user->id,
                'name' => 'Unifi 100Mbps',
                'type' => 'expense',
                'amount' => 13900, // RM139.00
                'account_id' => $cimb->id,
                'category_id' => $categories['Internet/Unifi']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 8,
                'start_date' => Carbon::now()->startOfMonth()->setDay(8),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(8)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(8) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(8),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Unifi fiber internet',
                'tags' => ['internet', 'unifi', 'monthly'],
            ],
            // Celcom postpaid
            [
                'user_id' => $user->id,
                'name' => 'Celcom Postpaid',
                'type' => 'expense',
                'amount' => 9800, // RM98.00
                'account_id' => $cimb->id,
                'category_id' => $categories['Mobile Phone']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 15,
                'start_date' => Carbon::now()->startOfMonth()->setDay(15),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(15)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(15) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(15),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Celcom Xpax postpaid plan',
                'tags' => ['phone', 'celcom', 'monthly'],
            ],
            // Netflix subscription
            [
                'user_id' => $user->id,
                'name' => 'Netflix Premium',
                'type' => 'expense',
                'amount' => 5490, // RM54.90
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Astro/Streaming']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 3,
                'start_date' => Carbon::now()->startOfMonth()->setDay(3),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(3)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(3) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(3),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Netflix Premium subscription',
                'tags' => ['netflix', 'subscription', 'entertainment'],
            ],
            // Spotify subscription
            [
                'user_id' => $user->id,
                'name' => 'Spotify Premium',
                'type' => 'expense',
                'amount' => 1590, // RM15.90
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Astro/Streaming']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 12,
                'start_date' => Carbon::now()->startOfMonth()->setDay(12),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(12)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(12) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(12),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Spotify music streaming',
                'tags' => ['spotify', 'subscription', 'music'],
            ],
            // Gym membership
            [
                'user_id' => $user->id,
                'name' => 'Anytime Fitness Membership',
                'type' => 'expense',
                'amount' => 15000, // RM150.00
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Gym/Fitness']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 1,
                'start_date' => Carbon::now()->startOfMonth(),
                'next_due_date' => Carbon::now()->startOfMonth()->addMonth(),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Gym membership auto renewal',
                'tags' => ['gym', 'fitness', 'health'],
            ],
            // Insurance - medical
            [
                'user_id' => $user->id,
                'name' => 'Great Eastern Medical Insurance',
                'type' => 'expense',
                'amount' => 45000, // RM450.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Insurance']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 20,
                'start_date' => Carbon::now()->startOfMonth()->setDay(20),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(20)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(20) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(20),
                'is_active' => true,
                'auto_create' => true,
                'notes' => 'Medical insurance premium',
                'tags' => ['insurance', 'medical', 'health'],
            ],
            // Investment - StashAway
            [
                'user_id' => $user->id,
                'name' => 'StashAway Monthly Investment',
                'type' => 'transfer',
                'amount' => 100000, // RM1,000.00
                'account_id' => $stashaway->id,
                'from_account_id' => $maybank->id,
                'to_account_id' => $stashaway->id,
                'category_id' => $categories['Investment']->id,
                'frequency' => 'monthly',
                'interval' => 1,
                'day_of_month' => 26,
                'start_date' => Carbon::now()->startOfMonth()->setDay(26),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(26)->isFuture() 
                    ? Carbon::now()->startOfMonth()->setDay(26) 
                    : Carbon::now()->startOfMonth()->addMonth()->setDay(26),
                'is_active' => true,
                'auto_create' => false,
                'reminder_days_before' => 1,
                'notes' => 'Monthly DCA investment',
                'tags' => ['investment', 'stashaway', 'dca'],
            ],
            // Parents allowance - weekly
            [
                'user_id' => $user->id,
                'name' => 'Parents Weekly Allowance',
                'type' => 'expense',
                'amount' => 20000, // RM200.00
                'account_id' => $cimb->id,
                'category_id' => $categories['Parents/Family']->id,
                'frequency' => 'weekly',
                'interval' => 1,
                'day_of_week' => 5, // Friday
                'start_date' => Carbon::now()->next(Carbon::FRIDAY),
                'next_due_date' => Carbon::now()->next(Carbon::FRIDAY),
                'is_active' => true,
                'auto_create' => false,
                'reminder_days_before' => 1,
                'notes' => 'Weekly allowance for parents',
                'tags' => ['family', 'parents', 'weekly'],
            ],
            // Quarterly car insurance
            [
                'user_id' => $user->id,
                'name' => 'Car Insurance (Quarterly)',
                'type' => 'expense',
                'amount' => 45000, // RM450.00
                'account_id' => $maybank->id,
                'category_id' => $categories['Insurance']->id,
                'frequency' => 'monthly',
                'interval' => 3, // Every 3 months
                'day_of_month' => 15,
                'start_date' => Carbon::now()->startOfMonth()->setDay(15),
                'next_due_date' => Carbon::now()->startOfMonth()->setDay(15)->addMonths(3),
                'is_active' => true,
                'auto_create' => false,
                'reminder_days_before' => 7,
                'notes' => 'Quarterly car insurance payment',
                'tags' => ['insurance', 'car', 'quarterly'],
            ],
            // Annual road tax
            [
                'user_id' => $user->id,
                'name' => 'Road Tax Renewal',
                'type' => 'expense',
                'amount' => 9000, // RM90.00
                'account_id' => $cimb->id,
                'category_id' => $categories['Car Maintenance']->id,
                'frequency' => 'yearly',
                'interval' => 1,
                'day_of_month' => 15,
                'month_of_year' => 6, // June
                'start_date' => Carbon::now()->setMonth(6)->setDay(15),
                'next_due_date' => Carbon::now()->year(Carbon::now()->month >= 6 ? Carbon::now()->year + 1 : Carbon::now()->year)->setMonth(6)->setDay(15),
                'is_active' => true,
                'auto_create' => false,
                'reminder_days_before' => 30,
                'notes' => 'Annual road tax renewal',
                'tags' => ['roadtax', 'car', 'annual'],
            ],
        ];

        foreach ($recurringTransactions as $recurring) {
            RecurringTransaction::create($recurring);
        }
    }
}