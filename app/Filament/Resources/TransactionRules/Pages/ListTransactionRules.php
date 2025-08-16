<?php

namespace App\Filament\Resources\TransactionRules\Pages;

use App\Filament\Resources\TransactionRules\TransactionRuleResource;
use App\Models\TransactionRule;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTransactionRules extends ListRecords
{
    protected static string $resource = TransactionRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('templates')
                ->label('Use Template')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->modalHeading('Create Rule from Template')
                ->modalDescription('Choose a template to quickly create a common rule')
                ->modalSubmitActionLabel('Create Rule')
                ->schema([
                    Select::make('template')
                        ->label('Template')
                        ->options([
                            'ride_sharing' => '🚗 Categorize Ride Sharing (Uber/Grab)',
                            'food_delivery' => '🍔 Categorize Food Delivery',
                            'subscriptions' => '📺 Tag Monthly Subscriptions',
                            'large_purchases' => '💰 Tag Large Purchases',
                            'coffee_shops' => '☕ Categorize Coffee Shops',
                            'online_shopping' => '🛍️ Categorize Online Shopping',
                            'salary' => '💵 Categorize Salary/Income',
                            'utilities' => '💡 Categorize Utilities',
                        ])
                        ->required()
                        ->helperText('Select a template to auto-fill the rule configuration'),
                ])
                ->action(function (array $data) {
                    $templates = [
                        'ride_sharing' => [
                            'name' => 'Categorize Ride Sharing',
                            'description' => 'Automatically categorize Uber and Grab transactions',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'regex', 'value' => 'UBER|GRAB|Uber|Grab'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'transport'],
                            ],
                            'apply_to' => 'expense',
                        ],
                        'food_delivery' => [
                            'name' => 'Categorize Food Delivery',
                            'description' => 'Automatically categorize food delivery services',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'regex', 'value' => 'FOODPANDA|GRABFOOD|DELIVEROO|FoodPanda|GrabFood'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'food-delivery'],
                            ],
                            'apply_to' => 'expense',
                        ],
                        'subscriptions' => [
                            'name' => 'Tag Monthly Subscriptions',
                            'description' => 'Tag recurring subscription payments',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'regex', 'value' => 'NETFLIX|SPOTIFY|YOUTUBE|DISNEY\\+|Netflix|Spotify'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'subscription'],
                                ['type' => 'add_tag', 'tag' => 'monthly'],
                            ],
                            'apply_to' => 'expense',
                        ],
                        'large_purchases' => [
                            'name' => 'Tag Large Purchases',
                            'description' => 'Tag transactions over MYR 500',
                            'conditions' => [
                                ['field' => 'amount', 'operator' => 'greater_than', 'value' => '500'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'large-purchase'],
                            ],
                            'apply_to' => 'all',
                        ],
                        'coffee_shops' => [
                            'name' => 'Categorize Coffee Shops',
                            'description' => 'Automatically categorize coffee shop purchases',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'regex', 'value' => 'STARBUCKS|COFFEE BEAN|COSTA|ZUS|Starbucks'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'coffee'],
                            ],
                            'apply_to' => 'expense',
                        ],
                        'online_shopping' => [
                            'name' => 'Categorize Online Shopping',
                            'description' => 'Automatically categorize online shopping',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'regex', 'value' => 'SHOPEE|LAZADA|AMAZON|ALIBABA|Shopee|Lazada'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'online-shopping'],
                            ],
                            'apply_to' => 'expense',
                        ],
                        'salary' => [
                            'name' => 'Categorize Salary',
                            'description' => 'Automatically categorize salary deposits',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'contains', 'value' => 'SALARY'],
                                ['field' => 'amount', 'operator' => 'greater_than', 'value' => '1000'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'salary'],
                            ],
                            'apply_to' => 'income',
                        ],
                        'utilities' => [
                            'name' => 'Categorize Utilities',
                            'description' => 'Automatically categorize utility bills',
                            'conditions' => [
                                ['field' => 'description', 'operator' => 'regex', 'value' => 'TNB|SYABAS|UNIFI|MAXIS|DIGI|CELCOM'],
                            ],
                            'actions' => [
                                ['type' => 'add_tag', 'tag' => 'utilities'],
                            ],
                            'apply_to' => 'expense',
                        ],
                    ];

                    $template = $templates[$data['template']] ?? null;

                    if ($template) {
                        $rule = TransactionRule::create([
                            ...$template,
                            'user_id' => auth()->id(),
                            'priority' => 50,
                            'is_active' => true,
                            'stop_processing' => false,
                        ]);

                        Notification::make()
                            ->title('Rule Created from Template')
                            ->success()
                            ->body("Created rule: {$rule->name}")
                            ->send();

                        return redirect(TransactionRuleResource::getUrl('edit', ['record' => $rule]));
                    }
                }),
            CreateAction::make(),
        ];
    }
}
