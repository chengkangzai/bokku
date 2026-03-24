<x-filament-widgets::widget>
    <x-filament::section heading="Smart Insights" icon="heroicon-o-light-bulb">
        <div class="space-y-3">
            @foreach($this->getInsights() as $insight)
                <div @class([
                    'flex items-start gap-3 rounded-lg p-3',
                    'bg-warning-50 dark:bg-warning-400/10' => $insight['type'] === 'warning',
                    'bg-success-50 dark:bg-success-400/10' => $insight['type'] === 'success',
                    'bg-info-50 dark:bg-info-400/10' => $insight['type'] === 'info',
                ])>
                    <x-filament::icon
                        :icon="$insight['icon']"
                        @class([
                            'h-5 w-5 shrink-0 mt-0.5',
                            'text-warning-500' => $insight['type'] === 'warning',
                            'text-success-500' => $insight['type'] === 'success',
                            'text-info-500' => $insight['type'] === 'info',
                        ])
                    />
                    <span class="text-sm">{{ $insight['message'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
