<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();

        if (! $user) {
            return;
        }

        $accounts = [
            [
                'user_id' => $user->id,
                'name' => 'Maybank Savings',
                'type' => 'bank',
                'initial_balance' => 5000, // $5,000.00
                'balance' => 5000,
                'currency' => 'MYR',
                'account_number' => '1234',
                'color' => '#3b82f6',
                'is_active' => true,
                'notes' => 'Main savings account',
            ],
            [
                'user_id' => $user->id,
                'name' => 'CIMB Current',
                'type' => 'bank',
                'initial_balance' => 2500, // $2,500.00
                'balance' => 2500,
                'currency' => 'MYR',
                'account_number' => '5678',
                'color' => '#ef4444',
                'is_active' => true,
                'notes' => 'Daily transactions account',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Wallet Cash',
                'type' => 'cash',
                'initial_balance' => 500, // $500.00
                'balance' => 500,
                'currency' => 'MYR',
                'account_number' => null,
                'color' => '#10b981',
                'is_active' => true,
                'notes' => 'Cash in wallet',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Visa Credit Card',
                'type' => 'credit_card',
                'initial_balance' => -1200, // -$1,200.00
                'balance' => -1200,
                'currency' => 'MYR',
                'account_number' => '9012',
                'color' => '#f59e0b',
                'is_active' => true,
                'notes' => 'Monthly credit card',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Car Loan',
                'type' => 'loan',
                'initial_balance' => -45000, // -$45,000.00
                'balance' => -45000,
                'currency' => 'MYR',
                'account_number' => 'AUTO-001',
                'color' => '#dc2626',
                'is_active' => true,
                'notes' => 'Car loan from Public Bank',
            ],
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
