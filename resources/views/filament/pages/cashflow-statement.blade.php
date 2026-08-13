<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => number_format($n, 2);
        $chip = function (array $values, bool $moreIsBad): string {
            $current = end($values);
            $previous = $values[count($values) - 2] ?? 0.0;
            if ($previous == 0.0) {
                return $current == 0.0
                    ? '<span class="cf-chip cf-chip-neutral">—</span>'
                    : '<span class="cf-chip cf-chip-neutral">new</span>';
            }
            $pct = round(($current - $previous) / abs($previous) * 100);
            if ($pct == 0) {
                return '<span class="cf-chip cf-chip-neutral">±0%</span>';
            }
            $bad = $moreIsBad ? $pct > 0 : $pct < 0;

            return '<span class="cf-chip '.($bad ? 'cf-chip-bad' : 'cf-chip-good').'">'.($pct > 0 ? '+' : '').$pct.'%</span>';
        };
        $lastIndex = count($cash['labels']) - 1;
        $currentIn = end($cash['in_total']);
        $currentOut = end($cash['out_total']);
        $currentNet = end($cash['net']);
        $currentClosing = end($cash['closing']);
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
                    <div class="cf-stat-label">Cash In</div>
                    <div class="cf-stat-value cf-good">MYR {{ $fmt($currentIn) }}</div>
                </div>
                <div class="cf-stat">
                    <div class="cf-stat-label">Cash Out</div>
                    <div class="cf-stat-value cf-bad">MYR {{ $fmt($currentOut) }}</div>
                </div>
                <div class="cf-stat">
                    <div class="cf-stat-label">Net Cash Flow</div>
                    <div class="cf-stat-value {{ $currentNet < 0 ? 'cf-bad' : 'cf-net' }}">{{ $currentNet < 0 ? '−' : '+' }}MYR {{ $fmt(abs($currentNet)) }}</div>
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
                            <th class="cf-num">Δ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="cf-section"><td colspan="{{ $lastIndex + 3 }}">Cash In</td></tr>
                        @forelse ($cash['cash_in'] as $row)
                            <tr>
                                <td>@if ($row['color'])<span class="cf-dot" style="background: {{ $row['color'] }}"></span>@endif{{ $row['name'] }}</td>
                                @foreach ($row['values'] as $index => $value)
                                    <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? '' : 'cf-prev' }}">{{ $value == 0.0 ? '—' : $fmt($value) }}</td>
                                @endforeach
                                <td class="cf-num">{!! $chip($row['values'], moreIsBad: false) !!}@if ($row['drillable']) <a href="{{ $drillUrl($row['id'], 'income') }}" target="_blank" rel="noopener" class="cf-drill" title="View transactions" aria-label="View {{ $row['name'] }} income transactions">↗</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $lastIndex + 3 }}" class="cf-empty">No cash received.</td></tr>
                        @endforelse
                        <tr class="cf-subtotal">
                            <td>Total Cash In</td>
                            @foreach ($cash['in_total'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-good' : 'cf-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                            <td class="cf-num">{!! $chip($cash['in_total'], moreIsBad: false) !!}</td>
                        </tr>

                        <tr class="cf-section"><td colspan="{{ $lastIndex + 3 }}">Cash Out</td></tr>
                        @forelse ($cash['cash_out'] as $row)
                            <tr>
                                <td>@if ($row['color'])<span class="cf-dot" style="background: {{ $row['color'] }}"></span>@endif{{ $row['name'] }}</td>
                                @foreach ($row['values'] as $index => $value)
                                    <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? '' : 'cf-prev' }}">{{ $value == 0.0 ? '—' : $fmt($value) }}</td>
                                @endforeach
                                <td class="cf-num">{!! $chip($row['values'], moreIsBad: true) !!}@if ($row['drillable']) <a href="{{ $drillUrl($row['id'], 'expense') }}" target="_blank" rel="noopener" class="cf-drill" title="View transactions" aria-label="View {{ $row['name'] }} expense transactions">↗</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $lastIndex + 3 }}" class="cf-empty">No cash spent.</td></tr>
                        @endforelse
                        <tr class="cf-subtotal">
                            <td>Total Cash Out</td>
                            @foreach ($cash['out_total'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-bad' : 'cf-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                            <td class="cf-num">{!! $chip($cash['out_total'], moreIsBad: true) !!}</td>
                        </tr>

                        <tr class="cf-net-row">
                            <td>Net Cash Flow</td>
                            @foreach ($cash['net'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? ($value < 0 ? 'cf-bad' : 'cf-net') : 'cf-prev' }}">{{ $value < 0 ? '−' : '+' }}{{ $fmt(abs($value)) }}</td>
                            @endforeach
                            <td class="cf-num">{!! $chip($cash['net'], moreIsBad: false) !!}</td>
                        </tr>
                        <tr class="cf-closing">
                            <td>Closing Cash</td>
                            @foreach ($cash['closing'] as $index => $value)
                                <td class="cf-num cf-c{{ $index }} {{ $index === $lastIndex ? 'cf-net' : 'cf-prev' }}">{{ $fmt($value) }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="cf-note">Cash basis over your bank and wallet accounts. Card charges are not cash out — the bill payment is. Loan repayments, family relay money and investment purchases all appear as cash movements. Closing cash always equals your live account balances.</p>
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
            --cf-chip-bad-bg: rgba(185, 28, 28, 0.1);
            --cf-chip-good-bg: rgba(21, 128, 61, 0.1);
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
            --cf-chip-bad-bg: rgba(239, 68, 68, 0.14);
            --cf-chip-good-bg: rgba(34, 197, 94, 0.14);
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
        .cf-table { width: 100%; border-collapse: collapse; font-size: 13px; max-width: 1000px; }
        .cf-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--cf-faint); font-weight: 600; padding: 4px 10px; border-bottom: 1px solid var(--cf-border); }
        .cf-table td { padding: 6px 10px; }
        .cf-table tbody tr:not(.cf-section):hover td { background: var(--cf-surface-2); }
        .cf-num { text-align: right; }
        .cf-prev { color: var(--cf-muted); }
        .cf-dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 8px; vertical-align: -1px; }
        .cf-section td { font-size: 10px; text-transform: uppercase; letter-spacing: 0.14em; color: var(--cf-accent); font-weight: 700; padding-top: 18px; border-bottom: 1px solid var(--cf-border); }
        .cf-subtotal td { border-top: 1px solid var(--cf-border); font-weight: 700; }
        .cf-net-row td { border-top: 2px solid var(--cf-text); font-weight: 700; font-size: 14px; padding-top: 10px; }
        .cf-closing td { color: var(--cf-muted); font-weight: 600; }
        .cf-empty { color: var(--cf-muted); }
        .cf-note { font-size: 11px; color: var(--cf-faint); margin: 14px 0 0; }
        .cf-chip { display: inline-block; font-size: 10px; font-weight: 600; border-radius: 999px; padding: 1px 7px; }
        .cf-chip-bad { background: var(--cf-chip-bad-bg); color: var(--cf-bad); }
        .cf-chip-good { background: var(--cf-chip-good-bg); color: var(--cf-good); }
        .cf-chip-neutral { background: var(--cf-surface-2); color: var(--cf-muted); }
        .cf-drill { color: var(--cf-faint); text-decoration: none; font-size: 12px; padding: 2px 5px; border-radius: 4px; }
        .cf-drill:hover { color: var(--cf-accent); background: var(--cf-surface-2); }
        @media (max-width: 1400px) { .cf-c0 { display: none; } }
        @media (max-width: 1100px) { .cf-c1 { display: none; } }
        @media (max-width: 700px) { .cf-c2 { display: none; } }
    </style>
    @endassets
</x-filament-panels::page>
