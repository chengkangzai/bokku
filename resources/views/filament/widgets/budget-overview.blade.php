<div class="filament-widget">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Budget Overview</h3>
            <a href="{{ route('filament.admin.resources.budgets.index') }}" 
               class="text-sm text-primary-600 hover:text-primary-500">
                View All
            </a>
        </div>
        
        @if($this->getBudgets()->isEmpty())
            <div class="text-center py-8 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1" />
                </svg>
                <h4 class="mt-2 text-sm font-medium text-gray-900">No active budgets</h4>
                <p class="mt-1 text-sm text-gray-500">Get started by creating your first budget</p>
                <div class="mt-4">
                    <a href="{{ route('filament.admin.resources.budgets.create') }}" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700">
                        Create Budget
                    </a>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @foreach($this->getBudgets() as $budget)
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full mr-2 
                                    {{ $budget->getStatus() === 'over' ? 'bg-red-500' : '' }}
                                    {{ $budget->getStatus() === 'near' ? 'bg-yellow-500' : '' }}
                                    {{ $budget->getStatus() === 'under' ? 'bg-green-500' : '' }}">
                                </div>
                                <span class="font-medium text-gray-900">{{ $budget->category->name }}</span>
                            </div>
                            <span class="text-sm text-gray-500 uppercase">{{ strtoupper($budget->period) }}</span>
                        </div>
                        
                        <div class="mb-3">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>{{ $budget->getFormattedSpent() }} of {{ $budget->getFormattedBudget() }}</span>
                                <span>{{ $budget->getProgressPercentage() }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all duration-300
                                    {{ $budget->getStatus() === 'over' ? 'bg-red-500' : '' }}
                                    {{ $budget->getStatus() === 'near' ? 'bg-yellow-500' : '' }}
                                    {{ $budget->getStatus() === 'under' ? 'bg-green-500' : '' }}"
                                    style="width: {{ min(100, $budget->getProgressPercentage()) }}%">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-sm">
                            @if($budget->isOverBudget())
                                <span class="text-red-600 font-medium">
                                    Over by {{ $budget->getFormattedRemaining() }}
                                </span>
                            @else
                                <span class="text-green-600">
                                    {{ $budget->getFormattedRemaining() }} remaining
                                </span>
                            @endif
                            
                            @if($budget->getStatus() === 'over')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Over Budget
                                </span>
                            @elseif($budget->getStatus() === 'near')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Near Limit
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    On Track
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>