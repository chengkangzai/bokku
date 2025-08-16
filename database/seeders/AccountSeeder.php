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
                'initial_balance' => 15000, // MYR15,000.00
                'balance' => 12345.67, // MYR12,345.67
                'currency' => 'MYR',
                'account_number' => '514711234567',
                'color' => '#FFD700',
                'is_active' => true,
                'notes' => 'Primary savings account',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Touch n Go eWallet',
                'type' => 'cash',
                'initial_balance' => 200, // MYR200.00
                'balance' => 85.30, // MYR85.30
                'currency' => 'MYR',
                'account_number' => null,
                'color' => '#4169E1',
                'is_active' => true,
                'notes' => 'TnG eWallet for daily transactions',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Maybank Visa',
                'type' => 'credit_card',
                'initial_balance' => 2500, // MYR2,500.00
                'balance' => 3489.20, // MYR3,489.20
                'currency' => 'MYR',
                'account_number' => '4511****3456',
                'color' => '#FF6347',
                'is_active' => true,
                'notes' => 'Credit limit: MYR10,000',
            ],
            [
                'user_id' => $user->id,
                'name' => 'Car Loan - Honda City',
                'type' => 'loan',
                'initial_balance' => 65000, // MYR65,000.00
                'balance' => 52340, // MYR52,340.00
                'currency' => 'MYR',
                'account_number' => 'HL-AUTO-2023001',
                'color' => '#8B0000',
                'is_active' => true,
                'notes' => 'Hong Leong car loan, 7 years @ 2.95%',
            ],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                [
                    'user_id' => $account['user_id'],
                    'name' => $account['name'],
                ],
                $account
            );
        }
    }

    private function createMinimalAccountsForUser(User $user): void
    {
        $accounts = [
            [
                'user_id' => $user->id,
                'name' => 'RHB Savings',
                'type' => 'bank',
                'initial_balance' => 8000, // MYR8,000.00
                'balance' => 6543.20, // MYR6,543.20
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
                'initial_balance' => 300, // MYR300.00
                'balance' => 150.50, // MYR150.50
                'currency' => 'MYR',
                'account_number' => null,
                'color' => '#228B22',
                'is_active' => true,
                'notes' => null,
            ],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                [
                    'user_id' => $account['user_id'],
                    'name' => $account['name'],
                ],
                $account
            );
        }
    }
}