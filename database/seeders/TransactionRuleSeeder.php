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
            // Auto-categorize Grab transactions
            [
                'user_id' => $user->id,
                'name' => 'Auto-categorize Grab rides',
                'priority' => 1,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains',
                        'value' => 'Grab',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Transportation']->id ?? null,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => 'transport,grab',
                    ],
                ],
                'apply_to' => 'expense',
                'is_active' => true,
            ],

            // Auto-categorize utility bills
            [
                'user_id' => $user->id,
                'name' => 'Auto-categorize utility bills',
                'priority' => 2,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains_any',
                        'value' => 'TNB,Unifi,Water Bill,Electricity',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Bills & Utilities']->id ?? null,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => 'utilities,monthly',
                    ],
                ],
                'apply_to' => 'expense',
                'is_active' => true,
            ],

            // Tag large purchases
            [
                'user_id' => $user->id,
                'name' => 'Tag Large Purchases',
                'priority' => 3,
                'conditions' => [
                    [
                        'field' => 'amount',
                        'operator' => 'greater_than',
                        'value' => '500',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'add_tags',
                        'value' => 'large-purchase',
                    ],
                ],
                'apply_to' => 'expense',
                'is_active' => true,
            ],

            // Auto-categorize salary
            [
                'user_id' => $user->id,
                'name' => 'Auto-categorize salary deposits',
                'priority' => 4,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains_any',
                        'value' => 'Salary,Monthly Salary,PAYROLL',
                    ],
                    [
                        'field' => 'amount',
                        'operator' => 'greater_than',
                        'value' => '5000',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Salary']->id ?? null,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => 'salary,monthly',
                    ],
                ],
                'apply_to' => 'income',
                'is_active' => true,
            ],

            // Auto-categorize subscriptions
            [
                'user_id' => $user->id,
                'name' => 'Auto-categorize subscriptions',
                'priority' => 5,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains_any',
                        'value' => 'Netflix,Spotify,Subscription,YouTube Premium',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Entertainment']->id ?? null,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => 'subscription,entertainment',
                    ],
                    [
                        'type' => 'set_recurring',
                        'value' => true,
                    ],
                ],
                'apply_to' => 'expense',
                'is_active' => true,
            ],

            // Auto-categorize groceries
            [
                'user_id' => $user->id,
                'name' => 'Auto-categorize grocery shopping',
                'priority' => 6,
                'conditions' => [
                    [
                        'field' => 'description',
                        'operator' => 'contains_any',
                        'value' => 'Tesco,AEON,Giant,Lotus,NSK,Village Grocer',
                    ],
                ],
                'actions' => [
                    [
                        'type' => 'set_category',
                        'value' => $categories['Groceries']->id ?? null,
                    ],
                    [
                        'type' => 'add_tags',
                        'value' => 'groceries,shopping',
                    ],
                ],
                'apply_to' => 'expense',
                'is_active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            TransactionRule::firstOrCreate(
                [
                    'user_id' => $rule['user_id'],
                    'name' => $rule['name'],
                ],
                $rule
            );
        }
    }
}
