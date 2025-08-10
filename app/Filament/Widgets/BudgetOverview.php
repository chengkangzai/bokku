<?php

namespace App\Filament\Widgets;

use App\Models\Budget;
use Filament\Widgets\Widget;

class BudgetOverview extends Widget
{
    protected static string $view = 'filament.widgets.budget-overview';

    protected static ?int $sort = 3;

    public function getBudgets()
    {
        return Budget::where('user_id', auth()->id())
            ->where('is_active', true)
            ->with(['category'])
            ->orderBy('created_at')
            ->get();
    }
}
