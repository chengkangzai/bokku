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
        $cimb = Account::where('user_id', $user->id)->where('name', 'CIMB Current')->first();
        $tng = Account::where('user_id', $user->id)->where('name', 'Touch n Go eWallet')->first();
        $cash = Account::where('user_id', $user->id)->where('name', 'Wallet Cash')->first();
        $maybankVisa = Account::where('user_id', $user->id)->where('name', 'Maybank Visa')->first();
        $aeonCard = Account::where('user_id', $user->id)->where('name', 'AEON Credit Card')->first();
        $carLoan = Account::where('user_id', $user->id)->where('name', 'Car Loan - Honda City')->first();

        // Get categories
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        // Current month transactions
        $currentMonth = Carbon::now()->startOfMonth();

        $transactions = [
            // Monthly salary
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 850000, // RM8,500.00
                'description' => 'Monthly Salary - January 2025',
                'date' => $currentMonth->copy(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Salary']->id,
                'reference' => 'SAL-202501',
                'is_reconciled' => true,
                'tags' => ['monthly', 'salary'],
            ],
            // Transfer to spending account
            [
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount' => 300000, // RM3,000.00
                'description' => 'Monthly budget transfer',
                'date' => $currentMonth->copy()->addDay(),
                'account_id' => $cimb->id,
                'from_account_id' => $maybank->id,
                'to_account_id' => $cimb->id,
                'is_reconciled' => true,
            ],
            // Car loan payment
            [
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount' => 145000, // RM1,450.00
                'description' => 'Car loan monthly payment',
                'date' => $currentMonth->copy()->addDays(5),
                'account_id' => $carLoan->id,
                'from_account_id' => $maybank->id,
                'to_account_id' => $carLoan->id,
                'category_id' => $categories['Loan Payment']->id,
                'reference' => 'AUTO-PAY-202501',
                'is_reconciled' => true,
                'tags' => ['car-loan', 'monthly'],
            ],
            // TNB electricity bill
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 32450, // RM324.50
                'description' => 'TNB Electricity Bill',
                'date' => $currentMonth->copy()->addDays(7),
                'account_id' => $cimb->id,
                'category_id' => $categories['Electricity (TNB)']->id,
                'reference' => 'TNB-01-2025',
                'is_reconciled' => true,
                'tags' => ['utilities', 'monthly'],
            ],
            // Unifi internet
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 13900, // RM139.00
                'description' => 'Unifi Fibre 100Mbps',
                'date' => $currentMonth->copy()->addDays(8),
                'account_id' => $cimb->id,
                'category_id' => $categories['Internet/Unifi']->id,
                'reference' => 'TM-UNI-202501',
                'is_reconciled' => true,
                'tags' => ['internet', 'monthly'],
            ],
            // Grab ride
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 2350, // RM23.50
                'description' => 'Grab - Pavilion to Home',
                'date' => Carbon::now()->subDays(10),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Grab/E-hailing']->id,
                'tags' => ['transport', 'grab'],
            ],
            // Petrol
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 15000, // RM150.00
                'description' => 'Petron Ron95 - Full Tank',
                'date' => Carbon::now()->subDays(9),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Petrol']->id,
                'reference' => 'PTN-983742',
                'tags' => ['petrol', 'car'],
            ],
            // TnG reload
            [
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount' => 10000, // RM100.00
                'description' => 'TnG eWallet Reload',
                'date' => Carbon::now()->subDays(8),
                'account_id' => $tng->id,
                'from_account_id' => $cimb->id,
                'to_account_id' => $tng->id,
                'tags' => ['ewallet', 'reload'],
            ],
            // Toll payment
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 750, // RM7.50
                'description' => 'MEX Highway Toll',
                'date' => Carbon::now()->subDays(8),
                'account_id' => $tng->id,
                'category_id' => $categories['Toll']->id,
                'tags' => ['toll', 'highway'],
            ],
            // Parking
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 500, // RM5.00
                'description' => 'Pavilion Parking',
                'date' => Carbon::now()->subDays(8),
                'account_id' => $tng->id,
                'category_id' => $categories['Parking']->id,
                'tags' => ['parking'],
            ],
            // Starbucks coffee
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 1850, // RM18.50
                'description' => 'Starbucks - Caramel Macchiato',
                'date' => Carbon::now()->subDays(7),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Restaurant']->id,
                'tags' => ['coffee', 'starbucks'],
            ],
            // Lotus groceries
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 23580, // RM235.80
                'description' => 'Lotus Weekly Groceries',
                'date' => Carbon::now()->subDays(6),
                'account_id' => $aeonCard->id,
                'category_id' => $categories['Groceries']->id,
                'reference' => 'LTS-492837',
                'tags' => ['groceries', 'weekly'],
            ],
            // GrabFood
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 3240, // RM32.40
                'description' => 'GrabFood - McDonalds',
                'date' => Carbon::now()->subDays(5),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Food Delivery']->id,
                'tags' => ['grabfood', 'delivery'],
            ],
            // Mamak breakfast
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 1250, // RM12.50
                'description' => 'Restoran Ali - Roti Canai & Teh Tarik',
                'date' => Carbon::now()->subDays(4),
                'account_id' => $cash->id,
                'category_id' => $categories['Mamak/Kopitiam']->id,
                'tags' => ['breakfast', 'mamak'],
            ],
            // ATM withdrawal
            [
                'user_id' => $user->id,
                'type' => 'transfer',
                'amount' => 50000, // RM500.00
                'description' => 'ATM Cash Withdrawal - Maybank Bangsar',
                'date' => Carbon::now()->subDays(4),
                'account_id' => $cash->id,
                'from_account_id' => $cimb->id,
                'to_account_id' => $cash->id,
                'reference' => 'ATM-938472',
                'tags' => ['atm', 'withdrawal'],
            ],
            // Watsons
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 8920, // RM89.20
                'description' => 'Watsons - Personal Care Items',
                'date' => Carbon::now()->subDays(3),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Personal Care']->id,
                'tags' => ['shopping', 'personal-care'],
            ],
            // Netflix subscription
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 5490, // RM54.90
                'description' => 'Netflix Premium Subscription',
                'date' => Carbon::now()->subDays(3),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Astro/Streaming']->id,
                'reference' => 'NETFLIX-202501',
                'tags' => ['subscription', 'netflix', 'monthly'],
            ],
            // Shopee purchase
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 15670, // RM156.70
                'description' => 'Shopee - Phone Case & Accessories',
                'date' => Carbon::now()->subDays(2),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Online Shopping']->id,
                'reference' => 'SPE-293847293',
                'tags' => ['shopee', 'online-shopping'],
            ],
            // Restaurant dinner
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 18500, // RM185.00
                'description' => 'The Chicken Rice Shop - Family Dinner',
                'date' => Carbon::now()->subDays(2),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Restaurant']->id,
                'tags' => ['dinner', 'family'],
            ],
            // Mobile phone bill
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 9800, // RM98.00
                'description' => 'Celcom Postpaid Xpax',
                'date' => Carbon::now()->subDay(),
                'account_id' => $cimb->id,
                'category_id' => $categories['Mobile Phone']->id,
                'reference' => 'CEL-202501',
                'is_reconciled' => true,
                'tags' => ['phone', 'monthly'],
            ],
            // Gym membership
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 15000, // RM150.00
                'description' => 'Anytime Fitness Monthly',
                'date' => Carbon::now()->subDay(),
                'account_id' => $maybankVisa->id,
                'category_id' => $categories['Gym/Fitness']->id,
                'tags' => ['gym', 'monthly'],
            ],
            // Insurance payment
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 45000, // RM450.00
                'description' => 'Great Eastern Medical Insurance',
                'date' => Carbon::now(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Insurance']->id,
                'reference' => 'GE-MED-202501',
                'is_reconciled' => true,
                'tags' => ['insurance', 'medical', 'monthly'],
            ],
            // Investment
            [
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => 100000, // RM1,000.00
                'description' => 'StashAway Monthly Investment',
                'date' => Carbon::now(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Investment']->id,
                'reference' => 'SA-INV-202501',
                'tags' => ['investment', 'stashaway', 'monthly'],
            ],
        ];

        // Add some previous month transactions for comparison
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $previousMonthTransactions = [
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 850000, // RM8,500.00
                'description' => 'Monthly Salary - December 2024',
                'date' => $lastMonth->copy(),
                'account_id' => $maybank->id,
                'category_id' => $categories['Salary']->id,
                'reference' => 'SAL-202412',
                'is_reconciled' => true,
                'tags' => ['monthly', 'salary'],
            ],
            [
                'user_id' => $user->id,
                'type' => 'income',
                'amount' => 250000, // RM2,500.00
                'description' => 'Year End Bonus',
                'date' => $lastMonth->copy()->addDays(15),
                'account_id' => $maybank->id,
                'category_id' => $categories['Bonus']->id,
                'reference' => 'BONUS-2024',
                'is_reconciled' => true,
                'tags' => ['bonus', 'year-end'],
            ],
        ];

        foreach (array_merge($transactions, $previousMonthTransactions) as $transactionData) {
            // Extract tags before creating transaction
            $tags = $transactionData['tags'] ?? null;
            unset($transactionData['tags']);
            
            $transaction = Transaction::firstOrCreate(
                [
                    'user_id' => $transactionData['user_id'],
                    'description' => $transactionData['description'],
                    'date' => $transactionData['date'],
                    'amount' => $transactionData['amount'],
                ],
                $transactionData
            );
            
            // Attach tags if provided and transaction was newly created
            if ($tags && $transaction->wasRecentlyCreated) {
                $transaction->attachUserTag($tags);
            }
        }
    }
}
