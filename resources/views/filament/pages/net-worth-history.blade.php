<x-filament-panels::page>
    @php
        $fmt = fn (float $n): string => 'MYR '.number_format($n, 2);
        $signedFmt = fn (float $n): string => ($n < 0 ? '−' : '+').'MYR '.number_format(abs($n), 2);
    @endphp

    <div class="nw-root">
        <div class="nw-topbar">
            <div class="nw-stat">
                <div class="nw-stat-label">Net Worth</div>
                <div class="nw-stat-value {{ $netWorth < 0 ? 'nw-bad' : 'nw-net' }}">{{ ($netWorth < 0 ? '−' : '').'MYR '.number_format(abs($netWorth), 2) }}</div>
            </div>
            <div class="nw-stat">
                <div class="nw-stat-label">Total Assets</div>
                <div class="nw-stat-value nw-good">{{ $fmt($totalAssets) }}</div>
            </div>
            <div class="nw-stat">
                <div class="nw-stat-label">Total Liabilities</div>
                <div class="nw-stat-value nw-bad">{{ $fmt($totalLiabilities) }}</div>
            </div>
            @php $last = end($rows); @endphp
            @if ($last !== false && $last['change'] !== null)
                <div class="nw-stat">
                    <div class="nw-stat-label">This Month</div>
                    <div class="nw-stat-value {{ $last['change'] < 0 ? 'nw-bad' : 'nw-good' }}">{{ $signedFmt($last['change']) }}</div>
                </div>
            @endif
            <div class="nw-toggles">
                @if ($excludedCount > 0)
                    <div class="nw-toggle" role="group" aria-label="Net worth lens">
                        <button type="button" wire:click="$set('ownOnly', false)" aria-pressed="{{ $ownOnly ? 'false' : 'true' }}">All</button>
                        <button type="button" wire:click="$set('ownOnly', true)" aria-pressed="{{ $ownOnly ? 'true' : 'false' }}" title="Excludes {{ $excludedCount }} account(s) held for someone else">Own only</button>
                    </div>
                @endif
                <div class="nw-toggle" role="group" aria-label="History period">
                    @foreach ([6 => '6M', 12 => '12M', 24 => '24M'] as $value => $label)
                        <button type="button" wire:click="setMonths({{ $value }})" aria-pressed="{{ $months === $value ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        <section class="nw-card">
            <h2 class="nw-h2">Net Worth Over Time <span class="nw-hint">{{ $ownOnly ? 'own accounts only · ' : '' }}{{ $months }} months · hover for values</span></h2>
            <div
                wire:key="nw-chart-{{ $months }}-{{ $ownOnly ? 'own' : 'all' }}"
                x-data
                x-init="window.bokkuNetWorthChart($el)"
                data-config="{{ json_encode(['chart' => $chart, 'currency' => 'MYR']) }}"
            >
                <div class="nw-chart-wrap" wire:ignore><canvas data-nw-chart width="2080" height="560"></canvas></div>
                <div class="nw-readout" data-nw-readout wire:ignore>&nbsp;</div>
                <div class="nw-legend-keys">
                    <span><i class="nw-key" style="background: var(--nw-good)"></i>Assets</span>
                    <span><i class="nw-key" style="background: var(--nw-bad)"></i>Liabilities</span>
                    <span><i class="nw-key" style="background: var(--nw-net)"></i>Net worth (filled)</span>
                </div>
            </div>
        </section>

        <section class="nw-card">
            <h2 class="nw-h2">Month by Month</h2>
            <div class="nw-scroll-x">
                <table class="nw-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="nw-num">Assets</th>
                            <th class="nw-num">Liabilities</th>
                            <th class="nw-num">Net Worth</th>
                            <th class="nw-num">Change</th>
                            <th class="nw-num">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($rows) as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td class="nw-num">{{ $fmt($row['assets']) }}</td>
                                <td class="nw-num">{{ $fmt($row['liabilities']) }}</td>
                                <td class="nw-num {{ $row['value'] < 0 ? 'nw-bad' : '' }}">{{ ($row['value'] < 0 ? '−' : '').'MYR '.number_format(abs($row['value']), 2) }}</td>
                                <td class="nw-num {{ $row['change'] === null ? '' : ($row['change'] < 0 ? 'nw-bad' : 'nw-good') }}">
                                    {{ $row['change'] === null ? '—' : $signedFmt($row['change']) }}
                                </td>
                                <td class="nw-num">
                                    @if ($row['percent'] === null)
                                        <span class="nw-chip nw-chip-neutral">—</span>
                                    @elseif ($row['percent'] < 0)
                                        <span class="nw-chip nw-chip-bad">{{ $row['percent'] }}%</span>
                                    @else
                                        <span class="nw-chip nw-chip-good">+{{ $row['percent'] }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @assets
    <style>
        .nw-root {
            --nw-surface-2: rgba(0, 0, 0, 0.04);
            --nw-border: rgba(0, 0, 0, 0.1);
            --nw-text: #1b1e24;
            --nw-muted: #5c6370;
            --nw-faint: #9aa1ad;
            --nw-accent: #b45309;
            --nw-accent-soft: rgba(180, 83, 9, 0.1);
            --nw-good: #15803d;
            --nw-bad: #b91c1c;
            --nw-net: #1d4ed8;
            --nw-chip-bad-bg: rgba(185, 28, 28, 0.1);
            --nw-chip-good-bg: rgba(21, 128, 61, 0.1);
            --nw-grid: rgba(92, 99, 112, 0.16);
            --nw-fill: rgba(29, 78, 216, 0.12);
            display: grid;
            gap: 16px;
            font-variant-numeric: tabular-nums;
        }
        .dark .nw-root {
            --nw-surface-2: rgba(255, 255, 255, 0.05);
            --nw-border: rgba(255, 255, 255, 0.09);
            --nw-text: #e7e9ee;
            --nw-muted: #878e9a;
            --nw-faint: #565d68;
            --nw-accent: #f59e0b;
            --nw-accent-soft: rgba(245, 158, 11, 0.12);
            --nw-good: #22c55e;
            --nw-bad: #ef4444;
            --nw-net: #3b82f6;
            --nw-chip-bad-bg: rgba(239, 68, 68, 0.14);
            --nw-chip-good-bg: rgba(34, 197, 94, 0.14);
            --nw-grid: rgba(135, 142, 154, 0.14);
            --nw-fill: rgba(59, 130, 246, 0.14);
        }
        .nw-root { color: var(--nw-text); }
        .nw-topbar, .nw-card {
            border: 1px solid var(--nw-border);
            border-radius: 12px;
            padding: 16px 20px;
        }
        .dark .nw-topbar, .dark .nw-card { background: rgba(255, 255, 255, 0.02); }
        .nw-topbar { display: flex; flex-wrap: wrap; align-items: center; gap: 16px 36px; }
        .nw-stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--nw-muted); }
        .nw-stat-value { font-size: 17px; font-weight: 700; margin-top: 2px; }
        .nw-good { color: var(--nw-good); }
        .nw-bad { color: var(--nw-bad); }
        .nw-net { color: var(--nw-net); }
        .nw-toggles { display: flex; gap: 10px; margin-left: auto; flex-wrap: wrap; }
        .nw-toggle { display: inline-flex; border: 1px solid var(--nw-border); border-radius: 7px; overflow: hidden; }
        .nw-toggle button { background: transparent; border: 0; color: var(--nw-muted); font-size: 11px; padding: 6px 12px; cursor: pointer; }
        .nw-toggle button[aria-pressed="true"] { background: var(--nw-accent-soft); color: var(--nw-accent); font-weight: 700; }
        .nw-h2 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.14em; color: var(--nw-muted); margin: 0 0 14px; font-weight: 700; }
        .nw-hint { float: right; color: var(--nw-faint); font-weight: 400; letter-spacing: 0.02em; text-transform: none; }
        .nw-chart-wrap canvas { width: 100%; height: 280px; display: block; }
        .nw-readout { font-size: 11px; color: var(--nw-muted); min-height: 18px; margin-top: 8px; }
        .nw-legend-keys { display: flex; gap: 18px; font-size: 11px; color: var(--nw-muted); margin-top: 6px; flex-wrap: wrap; }
        .nw-key { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 6px; vertical-align: -1px; }
        .nw-readout b { color: var(--nw-text); }
        .nw-scroll-x { overflow-x: auto; }
        .nw-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .nw-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--nw-faint); font-weight: 600; padding: 4px 8px; border-bottom: 1px solid var(--nw-border); }
        .nw-table td { padding: 6px 8px; border-bottom: 1px solid var(--nw-border); }
        .nw-table tr:last-child td { border-bottom: 0; }
        .nw-table tbody tr:hover { background: var(--nw-surface-2); }
        .nw-num { text-align: right; }
        .nw-chip { display: inline-block; font-size: 10px; font-weight: 600; border-radius: 999px; padding: 1px 7px; }
        .nw-chip-bad { background: var(--nw-chip-bad-bg); color: var(--nw-bad); }
        .nw-chip-good { background: var(--nw-chip-good-bg); color: var(--nw-good); }
        .nw-chip-neutral { background: var(--nw-surface-2); color: var(--nw-muted); }
    </style>
    <script>
        window.bokkuNetWorthChart = function (root) {
            var config = JSON.parse(root.dataset.config);
            var labels = config.chart.labels;
            var values = config.chart.values;
            var assets = config.chart.assets;
            var liabilities = config.chart.liabilities;
            if (! labels.length) return;
            var currency = config.currency;
            var canvas = root.querySelector('[data-nw-chart]');
            var ctx = canvas.getContext('2d');
            var readout = root.querySelector('[data-nw-readout]');
            var PAD = { l: 110, r: 24, t: 24, b: 48 };
            var css = function (name) {
                return getComputedStyle(root.closest('.nw-root')).getPropertyValue(name).trim();
            };
            var fmt = function (n) {
                return currency + ' ' + n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            function draw(hover) {
                var W = canvas.width, H = canvas.height;
                ctx.clearRect(0, 0, W, H);
                var plotW = W - PAD.l - PAD.r, plotH = H - PAD.t - PAD.b;
                var all = values.concat(assets, liabilities);
                var maxV = Math.max.apply(null, all);
                var minV = Math.min.apply(null, all);
                var span = Math.max(1, maxV - minV);
                maxV += span * 0.1;
                minV -= span * 0.1;
                if (minV > 0 && minV < span * 0.5) minV = 0;
                var yOf = function (v) { return PAD.t + plotH * (1 - (v - minV) / (maxV - minV)); };
                var xOf = function (i) {
                    return labels.length === 1
                        ? PAD.l + plotW / 2
                        : PAD.l + plotW * (i / (labels.length - 1));
                };

                ctx.font = '22px ui-monospace, Menlo, monospace';
                ctx.lineWidth = 1;
                ctx.strokeStyle = css('--nw-grid');
                ctx.fillStyle = css('--nw-faint');
                var step = Math.pow(10, Math.floor(Math.log10(maxV - minV)));
                if ((maxV - minV) / step < 3) step /= 2;
                for (var v = Math.ceil(minV / step) * step; v <= maxV; v += step) {
                    var gy = yOf(v);
                    ctx.beginPath(); ctx.moveTo(PAD.l, gy); ctx.lineTo(W - PAD.r, gy); ctx.stroke();
                    ctx.textAlign = 'right';
                    ctx.fillText((v / 1000).toFixed(0) + 'k', PAD.l - 12, gy + 7);
                }

                if (minV < 0) {
                    ctx.strokeStyle = css('--nw-bad');
                    ctx.setLineDash([4, 6]);
                    ctx.beginPath(); ctx.moveTo(PAD.l, yOf(0)); ctx.lineTo(W - PAD.r, yOf(0)); ctx.stroke();
                    ctx.setLineDash([]);
                }

                ctx.beginPath();
                values.forEach(function (v, i) {
                    i === 0 ? ctx.moveTo(xOf(i), yOf(v)) : ctx.lineTo(xOf(i), yOf(v));
                });
                ctx.lineTo(xOf(values.length - 1), yOf(Math.max(minV, 0)));
                ctx.lineTo(xOf(0), yOf(Math.max(minV, 0)));
                ctx.closePath();
                ctx.fillStyle = css('--nw-fill');
                ctx.fill();

                var drawLine = function (series, color, width) {
                    ctx.strokeStyle = color;
                    ctx.lineWidth = width;
                    ctx.beginPath();
                    series.forEach(function (v, i) {
                        i === 0 ? ctx.moveTo(xOf(i), yOf(v)) : ctx.lineTo(xOf(i), yOf(v));
                    });
                    ctx.stroke();
                };

                drawLine(assets, css('--nw-good'), 2);
                drawLine(liabilities, css('--nw-bad'), 2);
                drawLine(values, css('--nw-net'), 3);

                values.forEach(function (v, i) {
                    ctx.beginPath();
                    ctx.arc(xOf(i), yOf(v), i === hover ? 8 : 4.5, 0, Math.PI * 2);
                    ctx.fillStyle = css('--nw-net');
                    ctx.fill();
                });

                if (hover >= 0) {
                    [[assets, '--nw-good'], [liabilities, '--nw-bad']].forEach(function (pair) {
                        ctx.beginPath();
                        ctx.arc(xOf(hover), yOf(pair[0][hover]), 6, 0, Math.PI * 2);
                        ctx.fillStyle = css(pair[1]);
                        ctx.fill();
                    });
                }

                var labelEvery = Math.ceil(labels.length / 12);
                ctx.fillStyle = css('--nw-muted');
                ctx.textAlign = 'center';
                labels.forEach(function (label, i) {
                    if (i % labelEvery !== 0 && i !== labels.length - 1) return;
                    ctx.fillStyle = i === hover ? css('--nw-accent') : css('--nw-muted');
                    ctx.fillText(label.split(' ')[0], xOf(i), H - 14);
                });
            }

            canvas.addEventListener('mousemove', function (e) {
                var rect = canvas.getBoundingClientRect();
                var x = (e.clientX - rect.left) * (canvas.width / rect.width);
                var plotW = canvas.width - PAD.l - PAD.r;
                var i = Math.round((x - PAD.l) / (labels.length === 1 ? plotW : plotW / (labels.length - 1)));
                if (i < 0 || i >= labels.length) { draw(-1); readout.innerHTML = '&nbsp;'; return; }
                draw(i);
                var change = i > 0 ? values[i] - values[i - 1] : null;
                readout.innerHTML = '<b>' + labels[i] + '</b> — assets <b style="color:var(--nw-good)">' + fmt(assets[i]) +
                    '</b> · liabilities <b style="color:var(--nw-bad)">' + fmt(liabilities[i]) +
                    '</b> · net <b style="color:var(--nw-net)">' + fmt(values[i]) + '</b>' +
                    (change === null ? '' : ' (' + (change < 0 ? '<b style="color:var(--nw-bad)">−' : '<b style="color:var(--nw-good)">+') + fmt(Math.abs(change)) + '</b> vs prior)');
            });
            canvas.addEventListener('mouseleave', function () { draw(-1); readout.innerHTML = '&nbsp;'; });

            draw(-1);
        };
    </script>
    @endassets
</x-filament-panels::page>
