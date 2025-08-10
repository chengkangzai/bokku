<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $ahmad = User::where('email', 'ahmad@example.com')->first();
        $sarah = User::where('email', 'sarah@example.com')->first();

        if ($ahmad) {
            $this->createAccountsForUser($ahmad);
        }

        if ($sarah) {
            $this->createMinimalAccountsForUser($sarah);
        }
    }

    private function createAccountsForUser(User $user): void
    {
        $accounts = [
            [
                'user_id' => $user->id,
                'name' => 'Maybank Savings',
                'type' => 'bank',
                'initial_balance' => 1500000, // RM15,000.00
                'balance' => 1234567, // RM12,345.67
                'currency' => 'MYR',
                'account_number' => '514711234567',
                'color' => '#FFD700',
                'is_active' => true,
                'notes' => 'Primary savings account for emergency fund',
            ],
            [
                'user_id' => $user->id,
                'name' => 'CIMB Current',
                'type' => 'bank',
                'initial_balance' => 500000, // RM5,000.00
                'balance' => 324150, // RM3,241.50
                'currency' => 'MYR',
                'account_number' => '7012345678',
                'color' => '#DC143C',
                'is_active' => true,
                'notes' => 'Daily spending account',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Public Bank Fixed Deposit',
                'type' => 'bank',
                'initial_balance' => 2000000, // RM20,000.00
                'balance' => 2000000,
                'currency' => 'MYR',
                'account_number' => 'FD-0012345',
                'color' => '#1E90FF',
                'is_active' => true,
                'notes' => '12-month fixed deposit @ 3.25% p.a.',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Touch n Go eWallet',
                'type' => 'cash',
                'initial_balance' => 20000, // RM200.00
                'balance' => 8530, // RM85.30
                'currency' => 'MYR',
                'account_number' => null,
                'color' => '#4169E1',
                'is_active' => true,
                'notes' => 'TnG eWallet for tolls and parking',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Wallet Cash',
                'type' => 'cash',
                'initial_balance' => 50000, // RM500.00
                'balance' => 32000, // RM320.00
                'currency' => 'MYR',
                'account_number' => null,
                'color' => '#32CD32',
                'is_active' => true,
                'notes' => 'Physical cash in wallet',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Maybank Visa',
                'type' => 'credit_card',
                'initial_balance' => -250000, // -RM2,500.00
                'balance' => -348920, // -RM3,489.20
                'currency' => 'MYR',
                'account_number' => '4511****3456',
                'color' => '#FF6347',
                'is_active' => true,
                'notes' => 'Credit limit: RM10,000',
            ],
            [
                'user_id' => $user->id,
                'name' => 'AEON Credit Card',
                'type' => 'credit_card',
                'initial_balance' => 0,
                'balance' => -123450, // -RM1,234.50
                'currency' => 'MYR',
                'account_number' => '5432****7890',
                'color' => '#FF1493',
                'is_active' => true,
                'notes' => 'For AEON shopping only',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Car Loan - Honda City',
                'type' => 'loan',
                'initial_balance' => -6500000, // -RM65,000.00
                'balance' => -5234000, // -RM52,340.00
                'currency' => 'MYR',
                'account_number' => 'HL-AUTO-2023001',
                'color' => '#8B0000',
                'is_active' => true,
                'notes' => 'Hong Leong car loan, 7 years @ 2.95%',
            ],
            [
                'user_id' => $user->id,
                'name' => 'ASB Loan',
                'type' => 'loan',
                'initial_balance' => -5000000, // -RM50,000.00
                'balance' => -4567800, // -RM45,678.00
                'currency' => 'MYR',
                'account_number' => 'ASB-2024-001234',
                'color' => '#4B0082',
                'is_active' => true,
                'notes' => 'ASB financing from Maybank',
            ],
            [
                'user_id' => $user->id,
                'name' => 'StashAway Investment',
                'type' => 'investment',
                'initial_balance' => 1000000, // RM10,000.00
                'balance' => 1123450, // RM11,234.50
                'currency' => 'MYR',
                'account_number' => 'SA-MY-001234',
                'color' => '#FFD700',
                'is_active' => true,
                'notes' => 'Risk Index 22%',
            ],
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }

    private function createMinimalAccountsForUser(User $user): void
    {
        $accounts = [
            [
                'user_id' => $user->id,
                'name' => 'RHB Savings',
                'type' => 'bank',
                'initial_balance' => 800000, // RM8,000.00
                'balance' => 654320, // RM6,543.20
                'currency' => 'MYR',
                'account_number' => '212345678901',
                'color' => '#00008B',
                'is_active' => true,
                'notes' => 'Main account',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Cash',
                'type' => 'cash',
                'initial_balance' => 30000, // RM300.00
                'balance' => 15050, // RM150.50
                'currency' => 'MYR',
                'account_number' => null,
                'color' => '#228B22',
                'is_active' => true,
                'notes' => null,
            ],
            [
                'user_id' => $user->id,
                'name' => 'Standard Chartered Card',
                'type' => 'credit_card',
                'initial_balance' => 0,
                'balance' => -89000, // -RM890.00
                'currency' => 'MYR',
                'account_number' => '5234****5678',
                'color' => '#FF4500',
                'is_active' => true,
                'notes' => 'Cashback card',
            ],
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
