<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => ($n < 0 ? '−' : '').number_format(abs($n), 2);
        $chip = function (array $values, bool $moreIsBad): string {
            $current = end($values);
            $previous = $values[count($values) - 2] ?? 0.0;
            if ($previous == 0.0) {
                return $current == 0.0
                    ? '<span class="bs-chip bs-chip-neutral">—</span>'
                    : '<span class="bs-chip bs-chip-neutral">new</span>';
            }
            $pct = round(($current - $previous) / abs($previous) * 100);
            if ($pct == 0) {
                return '<span class="bs-chip bs-chip-neutral">±0%</span>';
            }
            $bad = $moreIsBad ? $pct > 0 : $pct < 0;

            return '<span class="bs-chip '.($bad ? 'bs-chip-bad' : 'bs-chip-good').'">'.($pct > 0 ? '+' : '').$pct.'%</span>';
        };
        $lastIndex = count($sheet['labels']) - 1;
        $currentAssets = end($sheet['asset_total']);
        $currentLiabilities = end($sheet['liability_total']);
        $currentNetWorth = end($sheet['net_worth']);
    @endphp

    <div class="bs-root">
        <div class="bs-topbar">
            <div class="bs-pager">
                <button type="button" wire:click="previousMonth" aria-label="Previous month">‹</button>
                <span class="bs-month">{{ $monthLabel }}</span>
                <button type="button" wire:click="nextMonth" @disabled(! $this->canGoToNextMonth()) aria-label="Next month">›</button>
            </div>
            <div class="bs-stats">
                <div class="bs-stat">
                    <div class="bs-stat-label">Total Assets</div>
                    <div class="bs-stat-value bs-good">MYR {{ $fmt($currentAssets) }}</div>
                </div>
                <div class="bs-stat">
                    <div class="bs-stat-label">Total Liabilities</div>
                    <div class="bs-stat-value bs-bad">MYR {{ $fmt($currentLiabilities) }}</div>
                </div>
                <div class="bs-stat">
                    <div class="bs-stat-label">Net Worth</div>
                    <div class="bs-stat-value {{ $currentNetWorth < 0 ? 'bs-bad' : 'bs-net' }}">{{ $currentNetWorth < 0 ? '−' : '' }}MYR {{ number_format(abs($currentNetWorth), 2) }}</div>
                </div>
            </div>
        </div>

        <section class="bs-card">
            <div class="bs-scroll-x">
                <table class="bs-table">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($sheet['labels'] as $index => $label)
                                <th class="bs-num bs-c{{ $index }} {{ $index === $lastIndex ? '' : 'bs-prev' }}">{{ $label }}</th>
                            @endforeach
                            <th class="bs-num">Δ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bs-section"><td colspan="{{ $lastIndex + 3 }}">Assets</td></tr>
                        @php $currentGroup = null; @endphp
                        @foreach ($sheet['assets'] as $row)
                            @if ($row['group'] !== $currentGroup)
                                @php $currentGroup = $row['group']; @endphp
                                <tr class="bs-group"><td colspan="{{ $lastIndex + 3 }}">{{ $currentGroup }}</td></tr>
                            @endif
                            <tr>
                                <td class="bs-indent">{{ $row['name'] }}</td>
                                @foreach ($row['values'] as $index => $value)
                                    <td class="bs-num bs-c{{ $index }} {{ $index === $lastIndex ? '' : 'bs-prev' }}">{{ $fmt($value) }}</td>
                                @endforeach
                                <td class="bs-num">{!! $chip($row['values'], moreIsBad: false) !!}</td>
                            </tr>
                        @endforeach
                        <tr class="bs-subtotal">
                            <td>Total Assets</td>
                            @foreach ($sheet['asset_total'] as $index => $value)
                                <td class="bs-num bs-c{{ $index }} {{ $index === $lastIndex ? 'bs-good' : 'bs-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                            <td class="bs-num">{!! $chip($sheet['asset_total'], moreIsBad: false) !!}</td>
                        </tr>

                        <tr class="bs-section"><td colspan="{{ $lastIndex + 3 }}">Liabilities</td></tr>
                        @php $currentGroup = null; @endphp
                        @foreach ($sheet['liabilities'] as $row)
                            @if ($row['group'] !== $currentGroup)
                                @php $currentGroup = $row['group']; @endphp
                                <tr class="bs-group"><td colspan="{{ $lastIndex + 3 }}">{{ $currentGroup }}</td></tr>
                            @endif
                            <tr>
                                <td class="bs-indent">{{ $row['name'] }}</td>
                                @foreach ($row['values'] as $index => $value)
                                    <td class="bs-num bs-c{{ $index }} {{ $index === $lastIndex ? '' : 'bs-prev' }}">{{ $fmt($value) }}</td>
                                @endforeach
                                <td class="bs-num">{!! $chip($row['values'], moreIsBad: true) !!}</td>
                            </tr>
                        @endforeach
                        <tr class="bs-subtotal">
                            <td>Total Liabilities</td>
                            @foreach ($sheet['liability_total'] as $index => $value)
                                <td class="bs-num bs-c{{ $index }} {{ $index === $lastIndex ? 'bs-bad' : 'bs-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                            <td class="bs-num">{!! $chip($sheet['liability_total'], moreIsBad: true) !!}</td>
                        </tr>

                        <tr class="bs-net-row">
                            <td>Net Worth</td>
                            @foreach ($sheet['net_worth'] as $index => $value)
                                <td class="bs-num bs-c{{ $index }} {{ $index === $lastIndex ? ($value < 0 ? 'bs-bad' : 'bs-net') : 'bs-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                            <td class="bs-num">{!! $chip($sheet['net_worth'], moreIsBad: false) !!}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="bs-note">Balances as at each month end, in MYR. Liabilities show positive outstanding; a negative credit card balance means the card is in credit. "Receivables" and "Held for family" are the relayed family arrangements — they offset each other in net worth. The latest column always equals live account balances.</p>
        </section>
    </div>

    @assets
    <style>
        .bs-root {
            --bs-surface-2: rgba(0, 0, 0, 0.04);
            --bs-border: rgba(0, 0, 0, 0.1);
            --bs-text: #1b1e24;
            --bs-muted: #5c6370;
            --bs-faint: #9aa1ad;
            --bs-accent: #b45309;
            --bs-good: #15803d;
            --bs-bad: #b91c1c;
            --bs-net: #1d4ed8;
            --bs-chip-bad-bg: rgba(185, 28, 28, 0.1);
            --bs-chip-good-bg: rgba(21, 128, 61, 0.1);
            display: grid;
            gap: 16px;
            font-variant-numeric: tabular-nums;
        }
        .dark .bs-root {
            --bs-surface-2: rgba(255, 255, 255, 0.05);
            --bs-border: rgba(255, 255, 255, 0.09);
            --bs-text: #e7e9ee;
            --bs-muted: #878e9a;
            --bs-faint: #565d68;
            --bs-accent: #f59e0b;
            --bs-good: #22c55e;
            --bs-bad: #ef4444;
            --bs-net: #3b82f6;
            --bs-chip-bad-bg: rgba(239, 68, 68, 0.14);
            --bs-chip-good-bg: rgba(34, 197, 94, 0.14);
        }
        .bs-root { color: var(--bs-text); }
        .bs-topbar, .bs-card { border: 1px solid var(--bs-border); border-radius: 12px; padding: 16px 20px; }
        .dark .bs-topbar, .dark .bs-card { background: rgba(255, 255, 255, 0.02); }
        .bs-topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 28px; }
        .bs-pager { display: flex; align-items: center; gap: 10px; }
        .bs-pager button { background: var(--bs-surface-2); border: 1px solid var(--bs-border); color: var(--bs-text); width: 30px; height: 30px; border-radius: 7px; cursor: pointer; font-size: 14px; }
        .bs-pager button:hover:not(:disabled) { border-color: var(--bs-accent); color: var(--bs-accent); }
        .bs-pager button:disabled { opacity: 0.35; cursor: default; }
        .bs-month { font-size: 15px; font-weight: 700; min-width: 9ch; text-align: center; }
        .bs-stats { display: flex; gap: 28px; margin-left: auto; flex-wrap: wrap; }
        .bs-stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--bs-muted); }
        .bs-stat-value { font-size: 17px; font-weight: 700; margin-top: 2px; }
        .bs-good { color: var(--bs-good); }
        .bs-bad { color: var(--bs-bad); }
        .bs-net { color: var(--bs-net); }
        .bs-scroll-x { overflow-x: auto; }
        .bs-table { width: 100%; border-collapse: collapse; font-size: 13px; max-width: 1000px; }
        .bs-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--bs-faint); font-weight: 600; padding: 4px 10px; border-bottom: 1px solid var(--bs-border); }
        .bs-table td { padding: 6px 10px; }
        .bs-table tbody tr:not(.bs-section):not(.bs-group):hover td { background: var(--bs-surface-2); }
        .bs-num { text-align: right; }
        .bs-prev { color: var(--bs-muted); }
        .bs-indent { padding-left: 24px; }
        .bs-section td { font-size: 10px; text-transform: uppercase; letter-spacing: 0.14em; color: var(--bs-accent); font-weight: 700; padding-top: 18px; border-bottom: 1px solid var(--bs-border); }
        .bs-group td { font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--bs-faint); font-weight: 600; padding-top: 10px; }
        .bs-subtotal td { border-top: 1px solid var(--bs-border); font-weight: 700; }
        .bs-net-row td { border-top: 2px solid var(--bs-text); font-weight: 700; font-size: 14px; padding-top: 10px; }
        .bs-note { font-size: 11px; color: var(--bs-faint); margin: 14px 0 0; }
        .bs-chip { display: inline-block; font-size: 10px; font-weight: 600; border-radius: 999px; padding: 1px 7px; }
        .bs-chip-bad { background: var(--bs-chip-bad-bg); color: var(--bs-bad); }
        .bs-chip-good { background: var(--bs-chip-good-bg); color: var(--bs-good); }
        .bs-chip-neutral { background: var(--bs-surface-2); color: var(--bs-muted); }
        @media (max-width: 1400px) { .bs-c0 { display: none; } }
        @media (max-width: 1100px) { .bs-c1 { display: none; } }
        @media (max-width: 700px) { .bs-c2 { display: none; } }
    </style>
    @endassets
</x-filament-panels::page>
