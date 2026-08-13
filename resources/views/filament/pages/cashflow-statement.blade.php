<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => number_format($n, 2);
        $signed = fn (float $n): string => ($n < 0 ? '−' : '+').number_format(abs($n), 2);
        $lastIndex = count($cashflow['labels']) - 1;
        $currentFree = end($cashflow['free']);
        $currentNet = end($cashflow['net']);
        $currentDebt = end($cashflow['debt_total']);
        $currentIncome = end($cashflow['income']);
    @endphp

    <div class="cf-root">
        <div class="cf-topbar">
            <div class="cf-pager">
                <button type="button" wire:click="previousMonth" aria-label="Previous month">‹</button>
                <span class="cf-month">{{ $monthLabel }}</span>
                <button type="button" wire:click="nextMonth" @disabled(! $this->canGoToNextMonth()) aria-label="Next month">›</button>
            </div>
            <div class="cf-stats">
                <div class="cf-stat">
                    <div class="cf-stat-label">Net Income</div>
                    <div class="cf-stat-value {{ $currentNet < 0 ? 'cf-bad' : 'cf-net' }}">{{ $signed($currentNet) }}</div>
                </div>
                <div class="cf-stat">
                    <div class="cf-stat-label">Debt Service</div>
                    <div class="cf-stat-value cf-bad">−{{ $fmt($currentDebt) }}</div>
                </div>
                <div class="cf-stat">
                    <div class="cf-stat-label">Free Cashflow</div>
                    <div class="cf-stat-value {{ $currentFree < 0 ? 'cf-bad' : 'cf-good' }}">{{ $signed($currentFree) }}</div>
                </div>
                @if ($currentIncome > 0)
                    <div class="cf-stat">
                        <div class="cf-stat-label">Free Cash Rate</div>
                        <div class="cf-stat-value {{ $currentFree < 0 ? 'cf-bad' : 'cf-good' }}">{{ round($currentFree / $currentIncome * 100, 1) }}%</div>
                    </div>
                @endif
            </div>
        </div>

        <section class="cf-card">
            <div class="cf-scroll-x">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($cashflow['labels'] as $index => $label)
                                <th class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? '' : 'cf-prev' }}">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Income</td>
                            @foreach ($cashflow['income'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-good' : 'cf-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td>Expenses</td>
                            @foreach ($cashflow['expense'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-bad' : 'cf-prev' }}">−{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>
                        <tr class="cf-subtotal">
                            <td>Net Income</td>
                            @foreach ($cashflow['net'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? ($value < 0 ? 'cf-bad' : 'cf-net') : 'cf-prev' }}">{{ $signed($value) }}</td>
                            @endforeach
                        </tr>

                        <tr class="cf-section"><td colspan="{{ $lastIndex + 2 }}">Debt Service</td></tr>
                        @forelse ($cashflow['debt_service'] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                @foreach ($row['values'] as $index => $value)
                                    <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? '' : 'cf-prev' }}">{{ $value == 0.0 ? '—' : '−'.$fmt($value) }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ $lastIndex + 2 }}" class="cf-empty">No loan repayments recorded.</td></tr>
                        @endforelse
                        <tr class="cf-subtotal">
                            <td>Total Debt Service</td>
                            @foreach ($cashflow['debt_total'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-bad' : 'cf-prev' }}">−{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>

                        <tr class="cf-net-row">
                            <td>Free Cashflow</td>
                            @foreach ($cashflow['free'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? ($value < 0 ? 'cf-bad' : 'cf-good') : 'cf-prev' }}">{{ $signed($value) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="cf-note">All figures in MYR. Debt service counts transfers into your own loan accounts (loans held for someone else are excluded). Credit card bill payments are not listed - card spending is already counted in expenses when charged. Free cashflow is what remains after living costs and loan commitments.</p>
        </section>
    </div>

    @assets
    <style>
        .cf-root {
            --cf-surface-2: rgba(0, 0, 0, 0.04);
            --cf-border: rgba(0, 0, 0, 0.1);
            --cf-text: #1b1e24;
            --cf-muted: #5c6370;
            --cf-faint: #9aa1ad;
            --cf-accent: #b45309;
            --cf-good: #15803d;
            --cf-bad: #b91c1c;
            --cf-net: #1d4ed8;
            display: grid;
            gap: 16px;
            font-variant-numeric: tabular-nums;
        }
        .dark .cf-root {
            --cf-surface-2: rgba(255, 255, 255, 0.05);
            --cf-border: rgba(255, 255, 255, 0.09);
            --cf-text: #e7e9ee;
            --cf-muted: #878e9a;
            --cf-faint: #565d68;
            --cf-accent: #f59e0b;
            --cf-good: #22c55e;
            --cf-bad: #ef4444;
            --cf-net: #3b82f6;
        }
        .cf-root { color: var(--cf-text); }
        .cf-topbar, .cf-card { border: 1px solid var(--cf-border); border-radius: 12px; padding: 16px 20px; }
        .dark .cf-topbar, .dark .cf-card { background: rgba(255, 255, 255, 0.02); }
        .cf-topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 28px; }
        .cf-pager { display: flex; align-items: center; gap: 10px; }
        .cf-pager button { background: var(--cf-surface-2); border: 1px solid var(--cf-border); color: var(--cf-text); width: 30px; height: 30px; border-radius: 7px; cursor: pointer; font-size: 14px; }
        .cf-pager button:hover:not(:disabled) { border-color: var(--cf-accent); color: var(--cf-accent); }
        .cf-pager button:disabled { opacity: 0.35; cursor: default; }
        .cf-month { font-size: 15px; font-weight: 700; min-width: 9ch; text-align: center; }
        .cf-stats { display: flex; gap: 28px; margin-left: auto; flex-wrap: wrap; }
        .cf-stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--cf-muted); }
        .cf-stat-value { font-size: 17px; font-weight: 700; margin-top: 2px; }
        .cf-good { color: var(--cf-good); }
        .cf-bad { color: var(--cf-bad); }
        .cf-net { color: var(--cf-net); }
        .cf-scroll-x { overflow-x: auto; }
        .cf-table { width: 100%; border-collapse: collapse; font-size: 13px; max-width: 900px; }
        .cf-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--cf-faint); font-weight: 600; padding: 4px 10px; border-bottom: 1px solid var(--cf-border); }
        .cf-table td { padding: 6px 10px; }
        .cf-table tbody tr:not(.cf-section):hover td { background: var(--cf-surface-2); }
        .cf-num { text-align: right; }
        .cf-prev { color: var(--cf-muted); }
        .cf-section td { font-size: 10px; text-transform: uppercase; letter-spacing: 0.14em; color: var(--cf-accent); font-weight: 700; padding-top: 18px; border-bottom: 1px solid var(--cf-border); }
        .cf-subtotal td { border-top: 1px solid var(--cf-border); font-weight: 700; }
        .cf-net-row td { border-top: 2px solid var(--cf-text); font-weight: 700; font-size: 14px; padding-top: 10px; }
        .cf-empty { color: var(--cf-muted); }
        .cf-note { font-size: 11px; color: var(--cf-faint); margin: 14px 0 0; }
        @media (max-width: 1400px) { .cf-c0 { display: none; } }
        @media (max-width: 1100px) { .cf-c1 { display: none; } }
        @media (max-width: 700px) { .cf-c2 { display: none; } }
    </style>
    @endassets
</x-filament-panels::page>
