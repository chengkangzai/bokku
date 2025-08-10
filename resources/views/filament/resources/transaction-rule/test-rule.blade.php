<div class="p-4">
    @if($transactions->isEmpty())
        <p class="text-gray-500">No recent transactions match this rule.</p>
    @else
        <div class="space-y-2">
            <p class="text-sm text-gray-600 mb-3">Found {{ $transactions->count() }} matching transaction(s):</p>
            
            @foreach($transactions as $transaction)
                <div class="border rounded-lg p-3 bg-gray-50">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium">{{ $transaction->description }}</p>
                            <p class="text-sm text-gray-600">
                                {{ $transaction->date->format('M j, Y') }} • 
                                RM {{ number_format($transaction->amount, 2) }} •
                                {{ $transaction->account->name }}
                                @if($transaction->category)
                                    • {{ $transaction->category->name }}
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                            @if($transaction->type === 'income') bg-green-100 text-green-800
                            @elseif($transaction->type === 'expense') bg-red-100 text-red-800
                            @else bg-blue-100 text-blue-800
                            @endif">
                            {{ ucfirst($transaction->type) }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>