<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => 'MYR '.number_format($n, 2);
        $chip = function (?float $pct, bool $moreIsBad = true): string {
            if ($pct === null) {
                return '<span class="sa-chip sa-chip-neutral">—</span>';
            }
            $direction = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'neutral');
            $bad = $moreIsBad ? $pct > 0 : $pct < 0;
            $class = $direction === 'neutral' ? 'sa-chip-neutral' : ($bad ? 'sa-chip-bad' : 'sa-chip-good');
            $sign = $pct > 0 ? '+' : '';

            return "<span class=\"sa-chip {$class}\">{$sign}{$pct}%</span>";
        };
        $chartData = [
            'currency' => 'MYR',
            'breakdown' => $breakdown->map(fn ($row) => [
                'name' => $row->name,
                'color' => $row->color ?? '#565d68',
                'total' => (float) $row->total,
                'count' => (int) $row->count,
                'previous' => $row->previous !== null ? (float) $row->previous : null,
                'drillUrl' => $groupBy === 'category' ? $drillUrl($row->id ?? null) : null,
            ])->values()->all(),
            'trends' => $trends,
        ];
    @endphp

    <div class="sa-root">
        <div class="sa-topbar">
            <div class="sa-pager">
                <button type="button" wire:click="previousMonth" aria-label="Previous month">‹</button>
                <span class="sa-month">{{ $monthLabel }}</span>
                <button type="button" wire:click="nextMonth" @disabled(! $this->canGoToNextMonth()) aria-label="Next month">›</button>
            </div>
            <div class="sa-stats">
                <div class="sa-stat">
                    <div class="sa-stat-label">Income</div>
                    <div class="sa-stat-value sa-income">{{ $fmt($summary['income']) }}{!! $chip($summary['income_delta'], moreIsBad: false) !!}</div>
                </div>
                <div class="sa-stat">
                    <div class="sa-stat-label">Expenses</div>
                    <div class="sa-stat-value sa-expense">{{ $fmt($summary['expense']) }}{!! $chip($summary['expense_delta']) !!}</div>
                </div>
                <div class="sa-stat">
                    <div class="sa-stat-label">Net</div>
                    <div class="sa-stat-value sa-net">{{ $summary['net'] < 0 ? '−' : '+' }}{{ $fmt(abs($summary['net'])) }}</div>
                </div>
            </div>
        </div>

        <section class="sa-card">
            <h2 class="sa-h2">
                Spending Breakdown
                <span class="sa-hint">
                    click to exclude
                    @if ($hasTags)
                        ·
                        <span class="sa-toggle sa-toggle-inline" role="group" aria-label="Group by">
                            <button type="button" wire:click="setGroupBy('category')" aria-pressed="{{ $groupBy === 'category' ? 'true' : 'false' }}">category</button>
                            <button type="button" wire:click="setGroupBy('tag')" aria-pressed="{{ $groupBy === 'tag' ? 'true' : 'false' }}">tag</button>
                        </span>
                    @endif
                </span>
            </h2>
            <div
                wire:key="sa-breakdown-{{ $month }}-{{ $groupBy }}"
                x-data
                x-init="window.bokkuSpendingBreakdown($el)"
                data-config="{{ json_encode(['breakdown' => $chartData['breakdown'], 'currency' => 'MYR']) }}"
            >
                @if ($breakdown->isEmpty())
                    <p class="sa-empty">No expenses recorded for {{ $monthLabel }}.</p>
                @else
                    <div class="sa-breakdown" wire:ignore>
                        <div class="sa-donut-wrap">
                            <canvas data-sa-donut width="440" height="440"></canvas>
                            <div class="sa-donut-center">
                                <div class="sa-donut-sub" data-sa-donut-sub>Spent</div>
                                <div class="sa-donut-big" data-sa-donut-total></div>
                            </div>
                        </div>
                        <div class="sa-scroll-x">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>{{ $groupBy === 'tag' ? 'Tag' : 'Category' }}</th>
                                        <th class="sa-num">Amount</th>
                                        <th class="sa-num">%</th>
                                        <th class="sa-num">Δ prev</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-sa-legend></tbody>
                            </table>
                            <button type="button" class="sa-reset" data-sa-reset hidden>reset exclusions</button>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if ($movers !== [])
            <section class="sa-card">
                <h2 class="sa-h2">Top Movers <span class="sa-hint">largest change vs previous month</span></h2>
                <div class="sa-movers">
                    @foreach ($movers as $mover)
                        <div class="sa-mover">
                            <span><span class="sa-dot" style="background: {{ $mover['color'] ?? '#565d68' }}"></span>{{ $mover['name'] }}</span>
                            <span class="sa-mover-delta {{ $mover['change'] > 0 ? 'sa-worse' : 'sa-better' }}">
                                {{ $mover['change'] > 0 ? '+' : '−' }}{{ $fmt(abs($mover['change'])) }}
                            </span>
                            <span class="sa-mover-pct">{{ $mover['percent'] === null ? 'new' : ($mover['percent'] > 0 ? '+' : '').$mover['percent'].'%' }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="sa-card">
            <h2 class="sa-h2">
                Cashflow Trends
                <span class="sa-hint">
                    hover for values ·
                    <span class="sa-toggle sa-toggle-inline" role="group" aria-label="Trend period">
                        @foreach ([1 => '1M', 3 => '3M', 6 => '6M', 12 => '12M'] as $value => $label)
                            <button type="button" wire:click="setTrendMonths({{ $value }})" aria-pressed="{{ $trendMonths === $value ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </span>
                </span>
            </h2>
            <div
                wire:key="sa-trends-{{ $month }}-{{ $trendMonths }}"
                x-data
                x-init="window.bokkuSpendingTrends($el)"
                data-config="{{ json_encode(['trends' => $trends, 'currency' => 'MYR']) }}"
            >
                <div class="sa-trend-wrap" wire:ignore><canvas data-sa-trend width="2080" height="520"></canvas></div>
                <div class="sa-readout" data-sa-readout wire:ignore>&nbsp;</div>
                <div class="sa-legend-keys">
                    <span><i class="sa-key" style="background: var(--sa-income)"></i>Income</span>
                    <span><i class="sa-key" style="background: var(--sa-expense)"></i>Expenses</span>
                    <span><i class="sa-key" style="background: var(--sa-net)"></i>Net (line)</span>
                    @if ($trends['average_expense'] !== null)
                        <span><i class="sa-key sa-key-dashed"></i>Avg expense ({{ $fmt($trends['average_expense']) }}/mo)</span>
                    @endif
                </div>
            </div>
        </section>

        <section class="sa-card">
            <h2 class="sa-h2">Income Sources</h2>
            @if ($incomeSources->isEmpty())
                <p class="sa-empty">No income recorded for {{ $monthLabel }}.</p>
            @else
                <div class="sa-scroll-x">
                    <table class="sa-table">
                        <thead>
                            <tr><th>Category</th><th class="sa-num">Amount</th><th class="sa-num">Δ prev</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($incomeSources as $source)
                                <tr>
                                    <td><span class="sa-dot" style="background: {{ $source->color ?? '#565d68' }}"></span>{{ $source->name }}</td>
                                    <td class="sa-num">{{ $fmt($source->total) }}</td>
                                    <td class="sa-num">{!! $chip($source->previous !== null && $source->previous != 0.0 ? round(($source->total - $source->previous) / $source->previous * 100, 1) : null, moreIsBad: false) !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    @assets
    <style>
        .sa-root {
            --sa-surface-2: rgba(0, 0, 0, 0.04);
            --sa-border: rgba(0, 0, 0, 0.1);
            --sa-text: #1b1e24;
            --sa-muted: #5c6370;
            --sa-faint: #9aa1ad;
            --sa-accent: #b45309;
            --sa-accent-soft: rgba(180, 83, 9, 0.1);
            --sa-income: #15803d;
            --sa-expense: #b91c1c;
            --sa-net: #1d4ed8;
            --sa-chip-bad-bg: rgba(185, 28, 28, 0.1);
            --sa-chip-good-bg: rgba(21, 128, 61, 0.1);
            --sa-grid: rgba(92, 99, 112, 0.16);
            display: grid;
            gap: 16px;
            font-variant-numeric: tabular-nums;
        }
        .dark .sa-root {
            --sa-surface-2: rgba(255, 255, 255, 0.05);
            --sa-border: rgba(255, 255, 255, 0.09);
            --sa-text: #e7e9ee;
            --sa-muted: #878e9a;
            --sa-faint: #565d68;
            --sa-accent: #f59e0b;
            --sa-accent-soft: rgba(245, 158, 11, 0.12);
            --sa-income: #22c55e;
            --sa-expense: #ef4444;
            --sa-net: #3b82f6;
            --sa-chip-bad-bg: rgba(239, 68, 68, 0.14);
            --sa-chip-good-bg: rgba(34, 197, 94, 0.14);
            --sa-grid: rgba(135, 142, 154, 0.14);
        }
        .sa-root { color: var(--sa-text); }
        .sa-topbar, .sa-card {
            background: var(--fi-color-white, transparent);
            border: 1px solid var(--sa-border);
            border-radius: 12px;
            padding: 16px 20px;
        }
        .dark .sa-topbar, .dark .sa-card { background: rgba(255, 255, 255, 0.02); }
        .sa-topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 28px; }
        .sa-pager { display: flex; align-items: center; gap: 10px; }
        .sa-pager button {
            background: var(--sa-surface-2); border: 1px solid var(--sa-border); color: var(--sa-text);
            width: 30px; height: 30px; border-radius: 7px; cursor: pointer; font-size: 14px;
        }
        .sa-pager button:hover:not(:disabled) { border-color: var(--sa-accent); color: var(--sa-accent); }
        .sa-pager button:disabled { opacity: 0.35; cursor: default; }
        .sa-month { font-size: 15px; font-weight: 700; min-width: 9ch; text-align: center; }
        .sa-toggle { display: inline-flex; border: 1px solid var(--sa-border); border-radius: 7px; overflow: hidden; }
        .sa-toggle button { background: transparent; border: 0; color: var(--sa-muted); font-size: 11px; padding: 6px 12px; cursor: pointer; }
        .sa-toggle button[aria-pressed="true"] { background: var(--sa-accent-soft); color: var(--sa-accent); font-weight: 700; }
        .sa-toggle-inline button { padding: 2px 8px; font-size: 10px; }
        .sa-stats { display: flex; gap: 28px; margin-left: auto; flex-wrap: wrap; }
        .sa-stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--sa-muted); }
        .sa-stat-value { font-size: 17px; font-weight: 700; margin-top: 2px; }
        .sa-stat-value.sa-income { color: var(--sa-income); }
        .sa-stat-value.sa-expense { color: var(--sa-expense); }
        .sa-stat-value.sa-net { color: var(--sa-net); }
        .sa-chip { display: inline-block; font-size: 10px; font-weight: 600; border-radius: 999px; padding: 1px 7px; margin-left: 6px; vertical-align: 2px; }
        .sa-chip-bad { background: var(--sa-chip-bad-bg); color: var(--sa-expense); }
        .sa-chip-good { background: var(--sa-chip-good-bg); color: var(--sa-income); }
        .sa-chip-neutral { background: var(--sa-surface-2); color: var(--sa-muted); }
        .sa-h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.14em; color: var(--sa-muted); margin: 0 0 14px; font-weight: 700; }
        .sa-hint { float: right; color: var(--sa-faint); font-weight: 400; letter-spacing: 0.02em; text-transform: none; }
        .sa-empty { color: var(--sa-muted); font-size: 13px; margin: 0; }
        .sa-breakdown { display: grid; grid-template-columns: 240px 1fr; gap: 28px; align-items: center; }
        .sa-donut-wrap { position: relative; width: 220px; height: 220px; margin: 0 auto; }
        .sa-donut-wrap canvas { width: 220px; height: 220px; }
        .sa-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; text-align: center; }
        .sa-donut-big { font-size: 15px; font-weight: 700; }
        .sa-donut-sub { font-size: 10px; color: var(--sa-muted); text-transform: uppercase; letter-spacing: 0.1em; }
        .sa-scroll-x { overflow-x: auto; }
        .sa-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sa-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--sa-faint); font-weight: 600; padding: 4px 8px; border-bottom: 1px solid var(--sa-border); }
        .sa-table td { padding: 6px 8px; border-bottom: 1px solid var(--sa-border); }
        .sa-table tr:last-child td { border-bottom: 0; }
        .sa-num { text-align: right; }
        .sa-table tbody tr[data-sa-row] { cursor: pointer; }
        .sa-table tbody tr[data-sa-row]:hover, .sa-table tbody tr.sa-hl { background: var(--sa-surface-2); }
        .sa-table tbody tr.sa-excluded { opacity: 0.45; }
        .sa-table tbody tr.sa-excluded .sa-name { text-decoration: line-through; }
        .sa-table tbody tr.sa-excluded .sa-dot { background: transparent !important; box-shadow: inset 0 0 0 1.5px var(--sa-faint); }
        .sa-dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-right: 8px; vertical-align: -1px; }
        .sa-propbar { display: inline-block; vertical-align: middle; height: 4px; border-radius: 2px; background: var(--sa-surface-2); min-width: 80px; overflow: hidden; }
        .sa-propbar i { display: block; height: 100%; border-radius: 2px; }
        .sa-drill { color: var(--sa-faint); text-decoration: none; font-size: 12px; padding: 2px 6px; border-radius: 4px; }
        .sa-drill:hover { color: var(--sa-accent); background: var(--sa-accent-soft); }
        .sa-reset { background: none; border: 0; color: var(--sa-accent); font-size: 11px; cursor: pointer; padding: 4px 8px 0; text-decoration: underline; }
        .sa-movers { display: grid; gap: 4px; }
        .sa-mover { display: grid; grid-template-columns: 1fr auto auto; gap: 14px; align-items: baseline; padding: 6px 8px; border-radius: 6px; font-size: 13px; }
        .sa-mover:hover { background: var(--sa-surface-2); }
        .sa-mover-delta { font-weight: 700; }
        .sa-mover-delta.sa-worse { color: var(--sa-expense); }
        .sa-mover-delta.sa-better { color: var(--sa-income); }
        .sa-mover-pct { color: var(--sa-muted); font-size: 11px; min-width: 7ch; text-align: right; }
        .sa-trend-wrap canvas { width: 100%; height: 260px; display: block; }
        .sa-readout { font-size: 11px; color: var(--sa-muted); min-height: 18px; margin-top: 8px; }
        .sa-readout b { color: var(--sa-text); }
        .sa-legend-keys { display: flex; gap: 18px; font-size: 11px; color: var(--sa-muted); margin-top: 6px; flex-wrap: wrap; }
        .sa-key { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
        .sa-key-dashed { background: transparent; border: 1px dashed var(--sa-muted); }
        @media (max-width: 800px) {
            .sa-breakdown { grid-template-columns: 1fr; }
            .sa-stats { margin-left: 0; }
        }
    </style>
    <script>
        window.bokkuSaCss = function (el, name) {
            return getComputedStyle(el.closest('.sa-root')).getPropertyValue(name).trim();
        };
        window.bokkuSaFmt = function (currency, n) {
            return currency + ' ' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        window.bokkuSpendingBreakdown = function (root) {
            var config = JSON.parse(root.dataset.config);
            var rows = config.breakdown;
            if (! rows.length) return;
            var currency = config.currency;
            var fmt = window.bokkuSaFmt.bind(null, currency);
            var canvas = root.querySelector('[data-sa-donut]');
            var ctx = canvas.getContext('2d');
            var tbody = root.querySelector('[data-sa-legend]');
            var centerTotal = root.querySelector('[data-sa-donut-total]');
            var centerSub = root.querySelector('[data-sa-donut-sub]');
            var resetBtn = root.querySelector('[data-sa-reset]');
            var excluded = {};
            var slices = [];

            function includedTotal() {
                return rows.reduce(function (s, r) { return excluded[r.name] ? s : s + r.total; }, 0);
            }

            function buildSlices() {
                var included = rows.filter(function (r) { return ! excluded[r.name]; })
                    .slice().sort(function (a, b) { return b.total - a.total; });
                var head = included.slice(0, 6);
                var tail = included.slice(6);
                slices = head.map(function (r) { return { name: r.name, total: r.total, color: r.color }; });
                var tailSum = tail.reduce(function (s, r) { return s + r.total; }, 0);
                if (tailSum > 0) {
                    slices.push({ name: 'Other', total: Math.round(tailSum * 100) / 100, color: '#565d68', members: tail.map(function (r) { return r.name; }) });
                }
            }

            function sliceIdxFor(name) {
                for (var i = 0; i < slices.length; i++) {
                    if (slices[i].name === name) return i;
                    if (slices[i].members && slices[i].members.indexOf(name) !== -1) return i;
                }
                return -1;
            }

            function renderLegend() {
                var subtotal = includedTotal();
                var maxAmt = rows.reduce(function (m, r) { return excluded[r.name] ? m : Math.max(m, r.total); }, 0);
                tbody.innerHTML = '';
                rows.forEach(function (r) {
                    var off = !! excluded[r.name];
                    var tr = document.createElement('tr');
                    tr.dataset.saRow = r.name;
                    if (off) tr.className = 'sa-excluded';
                    var delta;
                    if (r.previous === null || r.previous === 0) {
                        delta = '<span class="sa-chip sa-chip-neutral">new</span>';
                    } else {
                        var pct = Math.round((r.total - r.previous) / r.previous * 100);
                        delta = pct === 0
                            ? '<span class="sa-chip sa-chip-neutral">±0%</span>'
                            : '<span class="sa-chip ' + (pct > 0 ? 'sa-chip-bad' : 'sa-chip-good') + '">' + (pct > 0 ? '+' : '') + pct + '%</span>';
                    }
                    var drill = r.drillUrl
                        ? ' <a href="' + r.drillUrl + '" target="_blank" rel="noopener" class="sa-drill" title="View transactions" aria-label="View ' + r.name + ' transactions">&#8599;</a>'
                        : '';
                    tr.innerHTML =
                        '<td><span class="sa-dot" style="background:' + r.color + '"></span><span class="sa-name">' + r.name + '</span></td>' +
                        '<td class="sa-num">' + fmt(r.total) + '</td>' +
                        '<td class="sa-num">' + (off || subtotal === 0 ? '—' : (r.total / subtotal * 100).toFixed(1) + '%') + '</td>' +
                        '<td class="sa-num">' + delta + '</td>' +
                        '<td class="sa-num" style="white-space:nowrap"><span class="sa-propbar"><i style="width:' + (off || maxAmt === 0 ? 0 : r.total / maxAmt * 100) + '%;background:' + r.color + '"></i></span>' + drill + '</td>';
                    tr.addEventListener('mouseenter', function () { if (! off) drawDonut(sliceIdxFor(r.name)); });
                    tr.addEventListener('mouseleave', function () { drawDonut(-1); });
                    tr.addEventListener('click', function (e) {
                        if (e.target.closest('.sa-drill')) return;
                        excluded[r.name] = ! excluded[r.name];
                        rerender();
                    });
                    tbody.appendChild(tr);
                });
                var n = Object.keys(excluded).filter(function (k) { return excluded[k]; }).length;
                resetBtn.hidden = n === 0;
                centerSub.textContent = n > 0 ? 'Spent · ' + n + ' excluded' : 'Spent';
            }

            function drawDonut(hl) {
                var subtotal = includedTotal();
                var W = canvas.width, R = W / 2, inner = R * 0.62;
                ctx.clearRect(0, 0, W, W);
                var start = -Math.PI / 2;
                slices.forEach(function (s, i) {
                    var ang = subtotal === 0 ? 0 : s.total / subtotal * Math.PI * 2;
                    ctx.beginPath();
                    ctx.moveTo(R, R);
                    ctx.arc(R, R, i === hl ? R : R - 10, start, start + ang);
                    ctx.closePath();
                    ctx.fillStyle = s.color;
                    ctx.globalAlpha = (hl >= 0 && i !== hl) ? 0.35 : 1;
                    ctx.fill();
                    start += ang;
                });
                ctx.globalAlpha = 1;
                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath();
                ctx.arc(R, R, inner, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalCompositeOperation = 'source-over';
                centerTotal.textContent = hl >= 0 ? fmt(slices[hl].total) : fmt(subtotal);
                tbody.querySelectorAll('tr').forEach(function (row) {
                    row.classList.toggle('sa-hl', hl >= 0 && sliceIdxFor(row.dataset.saRow) === hl && ! excluded[row.dataset.saRow]);
                });
            }

            function hitTest(e) {
                var rect = canvas.getBoundingClientRect();
                var x = (e.clientX - rect.left) * (canvas.width / rect.width) - canvas.width / 2;
                var y = (e.clientY - rect.top) * (canvas.height / rect.height) - canvas.height / 2;
                var d = Math.sqrt(x * x + y * y);
                if (d < canvas.width * 0.31 || d > canvas.width / 2) return -1;
                var a = Math.atan2(y, x) + Math.PI / 2;
                if (a < 0) a += Math.PI * 2;
                var subtotal = includedTotal();
                var acc = 0;
                for (var i = 0; i < slices.length; i++) {
                    acc += slices[i].total / subtotal * Math.PI * 2;
                    if (a <= acc) return i;
                }
                return -1;
            }

            function rerender() { buildSlices(); renderLegend(); drawDonut(-1); }

            canvas.addEventListener('mousemove', function (e) {
                var i = hitTest(e);
                canvas.style.cursor = i >= 0 ? 'pointer' : 'default';
                drawDonut(i);
            });
            canvas.addEventListener('click', function (e) {
                var i = hitTest(e);
                if (i < 0 || slices[i].members) return;
                excluded[slices[i].name] = true;
                rerender();
            });
            canvas.addEventListener('mouseleave', function () { drawDonut(-1); });
            resetBtn.addEventListener('click', function () { excluded = {}; rerender(); });

            rerender();
        };

        window.bokkuSpendingTrends = function (root) {
            var config = JSON.parse(root.dataset.config);
            var trends = config.trends;
            var fmt = window.bokkuSaFmt.bind(null, config.currency);
            var canvas = root.querySelector('[data-sa-trend]');
            var ctx = canvas.getContext('2d');
            var readout = root.querySelector('[data-sa-readout]');
            var PAD = { l: 90, r: 20, t: 20, b: 46 };
            var months = trends.labels;
            var income = trends.income;
            var expense = trends.expense;
            var net = trends.net;

            function draw(hover) {
                var W = canvas.width, H = canvas.height;
                ctx.clearRect(0, 0, W, H);
                var plotW = W - PAD.l - PAD.r, plotH = H - PAD.t - PAD.b;
                var maxV = Math.max(1, Math.max.apply(null, income.concat(expense))) * 1.08;
                var minV = Math.min(0, Math.min.apply(null, net)) * 1.25;
                var yOf = function (v) { return PAD.t + plotH * (1 - (v - minV) / (maxV - minV)); };
                var slot = plotW / months.length;

                ctx.font = '22px ui-monospace, Menlo, monospace';
                ctx.lineWidth = 1;
                ctx.strokeStyle = window.bokkuSaCss(root, '--sa-grid');
                ctx.fillStyle = window.bokkuSaCss(root, '--sa-faint');
                var step = maxV > 8000 ? 5000 : (maxV > 3000 ? 2000 : 1000);
                for (var v = 0; v <= maxV; v += step) {
                    var y = yOf(v);
                    ctx.beginPath(); ctx.moveTo(PAD.l, y); ctx.lineTo(W - PAD.r, y); ctx.stroke();
                    ctx.textAlign = 'right';
                    ctx.fillText((v / 1000) + 'k', PAD.l - 10, y + 7);
                }

                months.forEach(function (m, i) {
                    var cx = PAD.l + slot * i + slot / 2;
                    var bw = Math.min(slot * 0.16, 40);
                    ctx.globalAlpha = (hover >= 0 && hover !== i) ? 0.35 : 1;
                    ctx.fillStyle = window.bokkuSaCss(root, '--sa-income');
                    ctx.fillRect(cx - bw - 3, yOf(income[i]), bw, yOf(0) - yOf(income[i]));
                    ctx.fillStyle = window.bokkuSaCss(root, '--sa-expense');
                    ctx.fillRect(cx + 3, yOf(expense[i]), bw, yOf(0) - yOf(expense[i]));
                    ctx.globalAlpha = 1;
                    ctx.fillStyle = hover === i ? window.bokkuSaCss(root, '--sa-accent') : window.bokkuSaCss(root, '--sa-muted');
                    ctx.textAlign = 'center';
                    ctx.fillText(m.split(' ')[0], cx, H - 14);
                });

                if (trends.average_expense !== null) {
                    ctx.setLineDash([8, 8]);
                    ctx.strokeStyle = window.bokkuSaCss(root, '--sa-muted');
                    ctx.beginPath();
                    ctx.moveTo(PAD.l, yOf(trends.average_expense));
                    ctx.lineTo(W - PAD.r, yOf(trends.average_expense));
                    ctx.stroke();
                    ctx.setLineDash([]);
                }

                ctx.strokeStyle = window.bokkuSaCss(root, '--sa-net');
                ctx.lineWidth = 3;
                ctx.beginPath();
                net.forEach(function (v, i) {
                    var cx = PAD.l + slot * i + slot / 2;
                    i === 0 ? ctx.moveTo(cx, yOf(v)) : ctx.lineTo(cx, yOf(v));
                });
                ctx.stroke();
                net.forEach(function (v, i) {
                    var cx = PAD.l + slot * i + slot / 2;
                    ctx.beginPath();
                    ctx.arc(cx, yOf(v), i === hover ? 8 : 5, 0, Math.PI * 2);
                    ctx.fillStyle = window.bokkuSaCss(root, '--sa-net');
                    ctx.fill();
                });
            }

            canvas.addEventListener('mousemove', function (e) {
                var rect = canvas.getBoundingClientRect();
                var x = (e.clientX - rect.left) * (canvas.width / rect.width);
                var i = Math.floor((x - PAD.l) / ((canvas.width - PAD.l - PAD.r) / months.length));
                if (i < 0 || i >= months.length) { draw(-1); readout.innerHTML = '&nbsp;'; return; }
                draw(i);
                readout.innerHTML = '<b>' + months[i] + '</b> — income <b style="color:var(--sa-income)">' + fmt(income[i]) +
                    '</b> · expenses <b style="color:var(--sa-expense)">' + fmt(expense[i]) +
                    '</b> · net <b style="color:var(--sa-net)">' + (net[i] < 0 ? '−' : '+') + fmt(Math.abs(net[i])) + '</b>';
            });
            canvas.addEventListener('mouseleave', function () { draw(-1); readout.innerHTML = '&nbsp;'; });

            draw(-1);
        };
    </script>
    @endassets
</x-filament-panels::page>
