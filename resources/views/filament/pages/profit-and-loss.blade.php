<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => number_format($n, 2);
        $chip = function (float $current, float $previous, bool $moreIsBad): string {
            if ($previous == 0.0) {
                return $current == 0.0
                    ? '<span class="pl-chip pl-chip-neutral">—</span>'
                    : '<span class="pl-chip pl-chip-neutral">new</span>';
            }
            $pct = round(($current - $previous) / abs($previous) * 100);
            if ($pct == 0) {
                return '<span class="pl-chip pl-chip-neutral">±0%</span>';
            }
            $bad = $moreIsBad ? $pct > 0 : $pct < 0;

            return '<span class="pl-chip '.($bad ? 'pl-chip-bad' : 'pl-chip-good').'">'.($pct > 0 ? '+' : '').$pct.'%</span>';
        };
    @endphp

    <div class="pl-root">
        <div class="pl-topbar">
            <div class="pl-pager">
                <button type="button" wire:click="previousMonth" aria-label="Previous month">‹</button>
                <span class="pl-month">{{ $monthLabel }}</span>
                <button type="button" wire:click="nextMonth" @disabled(! $this->canGoToNextMonth()) aria-label="Next month">›</button>
            </div>
            <label class="pl-exclude">
                <input type="checkbox" wire:model.live="excludeCompany">
                Exclude company expenses
            </label>
            <div class="pl-stats">
                <div class="pl-stat">
                    <div class="pl-stat-label">Income</div>
                    <div class="pl-stat-value pl-good">MYR {{ $fmt($totals['income']['current']) }}</div>
                </div>
                <div class="pl-stat">
                    <div class="pl-stat-label">Expenses</div>
                    <div class="pl-stat-value pl-bad">MYR {{ $fmt($totals['expense']['current']) }}</div>
                </div>
                <div class="pl-stat">
                    <div class="pl-stat-label">Net</div>
                    <div class="pl-stat-value {{ $net['current'] < 0 ? 'pl-bad' : 'pl-net' }}">{{ $net['current'] < 0 ? '−' : '+' }}MYR {{ $fmt(abs($net['current'])) }}</div>
                </div>
                @if ($savingsRate !== null)
                    <div class="pl-stat">
                        <div class="pl-stat-label">Savings Rate</div>
                        <div class="pl-stat-value {{ $savingsRate < 0 ? 'pl-bad' : 'pl-good' }}">{{ $savingsRate }}%</div>
                    </div>
                @endif
            </div>
        </div>

        <section class="pl-card">
            <div class="pl-scroll-x">
                <table class="pl-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th class="pl-num">{{ $monthLabel }}</th>
                            <th class="pl-num pl-prev">{{ $previousLabel }}</th>
                            <th class="pl-num">Δ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="pl-section"><td colspan="4">Income</td></tr>
                        @forelse ($statement['income'] as $row)
                            <tr>
                                <td><span class="pl-dot" style="background: {{ $row['color'] ?? '#565d68' }}"></span>{{ $row['name'] }}</td>
                                <td class="pl-num">{{ $fmt($row['current']) }}</td>
                                <td class="pl-num pl-prev">{{ $fmt($row['previous']) }}</td>
                                <td class="pl-num">{!! $chip($row['current'], $row['previous'], moreIsBad: false) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pl-empty">No income recorded.</td></tr>
                        @endforelse
                        <tr class="pl-subtotal">
                            <td>Total Income</td>
                            <td class="pl-num pl-good">{{ $fmt($totals['income']['current']) }}</td>
                            <td class="pl-num pl-prev">{{ $fmt($totals['income']['previous']) }}</td>
                            <td class="pl-num">{!! $chip($totals['income']['current'], $totals['income']['previous'], moreIsBad: false) !!}</td>
                        </tr>

                        <tr class="pl-section"><td colspan="4">Expenses</td></tr>
                        @forelse ($statement['expense'] as $row)
                            <tr>
                                <td><span class="pl-dot" style="background: {{ $row['color'] ?? '#565d68' }}"></span>{{ $row['name'] }}</td>
                                <td class="pl-num">{{ $fmt($row['current']) }}</td>
                                <td class="pl-num pl-prev">{{ $fmt($row['previous']) }}</td>
                                <td class="pl-num">{!! $chip($row['current'], $row['previous'], moreIsBad: true) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="pl-empty">No expenses recorded.</td></tr>
                        @endforelse
                        <tr class="pl-subtotal">
                            <td>Total Expenses</td>
                            <td class="pl-num pl-bad">{{ $fmt($totals['expense']['current']) }}</td>
                            <td class="pl-num pl-prev">{{ $fmt($totals['expense']['previous']) }}</td>
                            <td class="pl-num">{!! $chip($totals['expense']['current'], $totals['expense']['previous'], moreIsBad: true) !!}</td>
                        </tr>

                        <tr class="pl-net-row">
                            <td>Net {{ $net['current'] < 0 ? 'Loss' : 'Income' }}</td>
                            <td class="pl-num {{ $net['current'] < 0 ? 'pl-bad' : 'pl-net' }}">{{ $net['current'] < 0 ? '−' : '+' }}{{ $fmt(abs($net['current'])) }}</td>
                            <td class="pl-num pl-prev">{{ $net['previous'] < 0 ? '−' : '+' }}{{ $fmt(abs($net['previous'])) }}</td>
                            <td class="pl-num">{!! $chip($net['current'], $net['previous'], moreIsBad: false) !!}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="pl-note">All figures in MYR. Transfers (loan repayments, savings moves, relayed money) are not income or expenses and never appear here.</p>
        </section>
    </div>

    @assets
    <style>
        .pl-root {
            --pl-surface-2: rgba(0, 0, 0, 0.04);
            --pl-border: rgba(0, 0, 0, 0.1);
            --pl-text: #1b1e24;
            --pl-muted: #5c6370;
            --pl-faint: #9aa1ad;
            --pl-accent: #b45309;
            --pl-good: #15803d;
            --pl-bad: #b91c1c;
            --pl-net: #1d4ed8;
            --pl-chip-bad-bg: rgba(185, 28, 28, 0.1);
            --pl-chip-good-bg: rgba(21, 128, 61, 0.1);
            display: grid;
            gap: 16px;
            font-variant-numeric: tabular-nums;
        }
        .dark .pl-root {
            --pl-surface-2: rgba(255, 255, 255, 0.05);
            --pl-border: rgba(255, 255, 255, 0.09);
            --pl-text: #e7e9ee;
            --pl-muted: #878e9a;
            --pl-faint: #565d68;
            --pl-accent: #f59e0b;
            --pl-good: #22c55e;
            --pl-bad: #ef4444;
            --pl-net: #3b82f6;
            --pl-chip-bad-bg: rgba(239, 68, 68, 0.14);
            --pl-chip-good-bg: rgba(34, 197, 94, 0.14);
        }
        .pl-root { color: var(--pl-text); }
        .pl-topbar, .pl-card { border: 1px solid var(--pl-border); border-radius: 12px; padding: 16px 20px; }
        .dark .pl-topbar, .dark .pl-card { background: rgba(255, 255, 255, 0.02); }
        .pl-topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 28px; }
        .pl-pager { display: flex; align-items: center; gap: 10px; }
        .pl-pager button { background: var(--pl-surface-2); border: 1px solid var(--pl-border); color: var(--pl-text); width: 30px; height: 30px; border-radius: 7px; cursor: pointer; font-size: 14px; }
        .pl-pager button:hover:not(:disabled) { border-color: var(--pl-accent); color: var(--pl-accent); }
        .pl-pager button:disabled { opacity: 0.35; cursor: default; }
        .pl-month { font-size: 15px; font-weight: 700; min-width: 9ch; text-align: center; }
        .pl-exclude { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--pl-muted); cursor: pointer; }
        .pl-exclude input { accent-color: var(--pl-accent); }
        .pl-stats { display: flex; gap: 28px; margin-left: auto; flex-wrap: wrap; }
        .pl-stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--pl-muted); }
        .pl-stat-value { font-size: 17px; font-weight: 700; margin-top: 2px; }
        .pl-good { color: var(--pl-good); }
        .pl-bad { color: var(--pl-bad); }
        .pl-net { color: var(--pl-net); }
        .pl-scroll-x { overflow-x: auto; }
        .pl-table { width: 100%; border-collapse: collapse; font-size: 13px; max-width: 720px; }
        .pl-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--pl-faint); font-weight: 600; padding: 4px 10px; border-bottom: 1px solid var(--pl-border); }
        .pl-table td { padding: 6px 10px; }
        .pl-num { text-align: right; }
        .pl-prev { color: var(--pl-muted); }
        .pl-dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 8px; vertical-align: -1px; }
        .pl-section td { font-size: 10px; text-transform: uppercase; letter-spacing: 0.14em; color: var(--pl-accent); font-weight: 700; padding-top: 18px; border-bottom: 1px solid var(--pl-border); }
        .pl-subtotal td { border-top: 1px solid var(--pl-border); font-weight: 700; }
        .pl-net-row td { border-top: 2px solid var(--pl-text); font-weight: 700; font-size: 14px; padding-top: 10px; }
        .pl-empty { color: var(--pl-muted); }
        .pl-note { font-size: 11px; color: var(--pl-faint); margin: 14px 0 0; }
        .pl-chip { display: inline-block; font-size: 10px; font-weight: 600; border-radius: 999px; padding: 1px 7px; }
        .pl-chip-bad { background: var(--pl-chip-bad-bg); color: var(--pl-bad); }
        .pl-chip-good { background: var(--pl-chip-good-bg); color: var(--pl-good); }
        .pl-chip-neutral { background: var(--pl-surface-2); color: var(--pl-muted); }
    </style>
    @endassets
</x-filament-panels::page>
