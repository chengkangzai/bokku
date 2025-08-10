<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\TransactionRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $ahmad = User::where('email', 'ahmad@example.com')->first();
        
        if ($ahmad) {
            $this->createRulesForAhmad($ahmad);
        }
    }

    private function createRulesForAhmad(User $user): void
    {
        // Get categories for this user
        $categories = Category::where('user_id', $user->id)->get()->keyBy('name');

        $rules = [
            // Salary rule
            [
                'user_id' => $user->id,
                'name' => 'Monthly Salary',
                'priority' => 1,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Monthly Salary',
                    ],
                    [
                        'field' => 'amount',
                        'operator' => '>',
                        'value' => '8000',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Salary']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['salary', 'monthly'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => true,
            ],
            // Grab rides
            [
                'user_id' => $user->id,
                'name' => 'Grab Rides',
                'priority' => 2,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'starts_with',
                        'value' => 'Grab',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Grab/E-hailing']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['transport', 'grab'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => false,
            ],
            // Touch n Go transactions
            [
                'user_id' => $user->id,
                'name' => 'TnG Toll Payments',
                'priority' => 3,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Toll',
                    ],
                    [
                        'field' => 'account',
                        'operator' => 'equals',
                        'value' => 'Touch n Go eWallet',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Toll']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['toll', 'tng'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => true,
            ],
            // Netflix subscription
            [
                'user_id' => $user->id,
                'name' => 'Netflix Subscription',
                'priority' => 4,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Netflix',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Astro/Streaming']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['subscription', 'netflix', 'monthly'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => true,
            ],
            // Utilities - TNB
            [
                'user_id' => $user->id,
                'name' => 'TNB Electricity',
                'priority' => 5,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'TNB',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Electricity (TNB)']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['utilities', 'tnb', 'monthly'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => true,
            ],
            // Petrol
            [
                'user_id' => $user->id,
                'name' => 'Petrol Station',
                'priority' => 6,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'regex',
                        'value' => '(Petron|Shell|Petronas|BHP|Caltex)',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Petrol']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['petrol', 'car'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => false,
            ],
            // Shopee/Lazada
            [
                'user_id' => $user->id,
                'name' => 'Online Shopping',
                'priority' => 7,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'regex',
                        'value' => '(Shopee|Lazada|Zalora)',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Online Shopping']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['online-shopping'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => false,
            ],
            // Coffee shops
            [
                'user_id' => $user->id,
                'name' => 'Coffee Shops',
                'priority' => 8,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'regex',
                        'value' => '(Starbucks|Coffee Bean|Costa|ZUS)',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Restaurant']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['coffee'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => false,
            ],
            // Insurance payments
            [
                'user_id' => $user->id,
                'name' => 'Insurance Premium',
                'priority' => 9,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'regex',
                        'value' => '(Great Eastern|Prudential|AIA|Allianz|Insurance)',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Insurance']->id,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => ['insurance', 'monthly'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => true,
            ],
            // ATM withdrawals
            [
                'user_id' => $user->id,
                'name' => 'ATM Cash Withdrawal',
                'priority' => 10,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'ATM',
                    ],
                    [
                        'field' => 'type',
                        'operator' => 'equals',
                        'value' => 'transfer',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tags',
                        'value' => ['atm', 'cash-withdrawal'],
                    ],
                ],
                'is_active' => true,
                'stop_processing' => false,
            ],
            // Disabled rule example
            [
                'user_id' => $user->id,
                'name' => 'Old Gym Membership (Cancelled)',
                'priority' => 99,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Celebrity Fitness',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Gym/Fitness']->id,
                    ],
                ],
                'is_active' => false,
                'stop_processing' => true,
            ],
        ];

        foreach ($rules as $rule) {
            TransactionRule::create($rule);
        }
    }
}