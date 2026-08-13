<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => number_format($n, 2);
        $flow = function (float $n): string {
            if ($n == 0.0) {
                return '<span class="cf-prev">—</span>';
            }
            $class = $n < 0 ? 'cf-bad' : 'cf-good';

            return '<span class="'.$class.'">'.($n < 0 ? '−' : '+').number_format(abs($n), 2).'</span>';
        };
        $lastIndex = count($cash['labels']) - 1;
        $currentClosing = end($cash['closing']);
        $currentOpening = $cash['opening'][$lastIndex];
        $netChange = round($currentClosing - $currentOpening, 2);
        $rows = [
            ['label' => 'Income received', 'key' => 'income'],
            ['label' => 'Expenses (cash & wallet)', 'key' => 'expenses'],
            ['label' => 'Card bill payments', 'key' => 'card_payments'],
            ['label' => 'Loan repayments', 'key' => 'loan_payments'],
            ['label' => 'Family / relay (net)', 'key' => 'relay'],
            ['label' => 'Other (net)', 'key' => 'other'],
        ];
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
                    <div class="cf-stat-label">Opening Cash</div>
                    <div class="cf-stat-value">MYR {{ $fmt($currentOpening) }}</div>
                </div>
                <div class="cf-stat">
                    <div class="cf-stat-label">Net Change</div>
                    <div class="cf-stat-value {{ $netChange < 0 ? 'cf-bad' : 'cf-good' }}">{{ $netChange < 0 ? '−' : '+' }}MYR {{ $fmt(abs($netChange)) }}</div>
                </div>
                <div class="cf-stat">
                    <div class="cf-stat-label">Closing Cash</div>
                    <div class="cf-stat-value cf-net">MYR {{ $fmt($currentClosing) }}</div>
                </div>
            </div>
        </div>

        <section class="cf-card">
            <div class="cf-scroll-x">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($cash['labels'] as $index => $label)
                                <th class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? '' : 'cf-prev' }}">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="cf-opening">
                            <td>Opening Cash</td>
                            @foreach ($cash['opening'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} cf-prev">{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>
                        @foreach ($rows as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                @foreach ($cash[$row['key']] as $index => $value)
                                    <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? '' : 'cf-dim' }}">{!! $flow($value) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr class="cf-net-row">
                            <td>Closing Cash</td>
                            @foreach ($cash['closing'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-net' : 'cf-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="cf-note">Cash = your bank and wallet accounts (accounts held for someone else and investments excluded). Card charges hit Expenses on the P&L when spent; here only the actual bill payment moves cash. "Family / relay" is money exchanged with held-for-others accounts, e.g. mum's loans. Opening + flows always equals closing, and the latest closing equals your live account balances.</p>
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
        .cf-table tbody tr:hover td { background: var(--cf-surface-2); }
        .cf-num { text-align: right; }
        .cf-prev { color: var(--cf-muted); }
        .cf-dim { opacity: 0.65; }
        .cf-opening td { border-bottom: 1px solid var(--cf-border); color: var(--cf-muted); }
        .cf-net-row td { border-top: 2px solid var(--cf-text); font-weight: 700; font-size: 14px; padding-top: 10px; }
        .cf-note { font-size: 11px; color: var(--cf-faint); margin: 14px 0 0; }
        @media (max-width: 1400px) { .cf-c0 { display: none; } }
        @media (max-width: 1100px) { .cf-c1 { display: none; } }
        @media (max-width: 700px) { .cf-c2 { display: none; } }
    </style>
    @endassets
</x-filament-panels::page>
