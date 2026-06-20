@extends('layouts.app')
@section('title', 'Terminal')

@section('body')
<div class="h-full flex flex-col">
    {{-- Top bar --}}
    <header class="bg-panel border-b border-edge px-4 py-2 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-4">
            <span class="font-bold text-white">{{ config('app.name') }}</span>
            @if ($account)
                <span class="text-xs text-gray-400">
                    #{{ $account->login }} · {{ strtoupper($account->type) }} · 1:{{ $account->leverage }} · {{ $account->currency }}
                </span>
            @endif
        </div>

        @if ($account)
        <div class="flex items-center gap-4 text-xs" id="account-bar">
            <div>Balance: <span class="font-semibold text-white" data-acc="balance">—</span></div>
            <div>Equity: <span class="font-semibold text-white" data-acc="equity">—</span></div>
            <div>Margin: <span class="font-semibold text-white" data-acc="used_margin">—</span></div>
            <div>Free: <span class="font-semibold text-white" data-acc="free_margin">—</span></div>
            <div>Level: <span class="font-semibold text-white" data-acc="margin_level">—</span></div>
        </div>
        @endif

        <div class="flex items-center gap-3 text-xs">
            <a href="{{ route('history') }}" class="text-gray-300 hover:text-white">History</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-gray-300 hover:text-white">Logout</button>
            </form>
        </div>
    </header>

    @if (! $account)
        <div class="p-6 text-sm text-down">No trading account is provisioned for your user.</div>
    @else
    <div class="flex-1 grid grid-cols-12 gap-px bg-edge overflow-hidden">
        {{-- Market Watch --}}
        <section class="col-span-12 md:col-span-4 lg:col-span-3 bg-panel flex flex-col overflow-hidden">
            <div class="px-3 py-2 text-xs uppercase tracking-wide text-gray-400 border-b border-edge flex items-center justify-between">
                <span>Market Watch</span>
                <span class="text-[10px] text-gray-600 normal-case" id="mw-count">{{ $instruments->count() }} symbols</span>
            </div>

            {{-- Symbol search --}}
            <div class="px-2 py-1.5 border-b border-edge">
                <input id="mw-search" type="text" placeholder="Search symbol" autocomplete="off"
                    class="w-full bg-panel2 border border-edge rounded-md px-2 py-1 text-xs focus:outline-none focus:border-accent">
            </div>

            {{-- Symbols list --}}
            <div id="mw-symbols" class="overflow-auto flex-1">
                <table class="w-full text-xs">
                    <thead class="text-gray-500 sticky top-0 bg-panel">
                        <tr>
                            <th class="text-left px-3 py-1.5 font-medium">Symbol</th>
                            <th class="text-right px-2 py-1.5 font-medium">Bid</th>
                            <th class="text-right px-2 py-1.5 font-medium">Ask</th>
                            <th class="text-right px-3 py-1.5 font-medium">Chg%</th>
                        </tr>
                    </thead>
                    <tbody id="watchlist">
                        @foreach ($instruments as $ins)
                        <tr class="mw-row border-t border-edge/50 hover:bg-panel2 cursor-pointer select-none"
                            data-symbol="{{ $ins->symbol }}" data-digits="{{ $ins->digits }}">
                            <td class="px-3 py-1.5 font-medium text-gray-200 whitespace-nowrap">
                                <span data-field="arrow" class="text-gray-600">●</span> {{ $ins->symbol }}
                            </td>
                            <td class="px-2 py-1.5 text-right tabular-nums" data-field="bid">—</td>
                            <td class="px-2 py-1.5 text-right tabular-nums" data-field="ask">—</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-500" data-field="change">—</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Tick chart --}}
            <div id="mw-tickchart" class="hidden flex-1 flex-col p-2">
                <div class="text-xs text-gray-300 mb-1" id="tick-title">Select a symbol</div>
                <canvas id="tick-canvas" class="flex-1 w-full bg-panel2 border border-edge rounded"></canvas>
                <div class="flex justify-between text-[10px] text-gray-500 mt-1 tabular-nums">
                    <span id="tick-min">—</span><span id="tick-last" class="text-gray-300">—</span><span id="tick-max">—</span>
                </div>
            </div>

            {{-- Bottom tabs --}}
            <div class="flex border-t border-edge text-xs shrink-0">
                <button data-mwtab="symbols" class="mw-tab flex-1 py-1.5 text-accent border-t-2 border-accent bg-panel2">Symbols</button>
                <button data-mwtab="tickchart" class="mw-tab flex-1 py-1.5 text-gray-400 border-t-2 border-transparent hover:text-gray-200">Tick Chart</button>
            </div>
        </section>

        {{-- Order panel --}}
        <section class="col-span-12 md:col-span-8 lg:col-span-3 bg-panel p-4 flex flex-col gap-3 overflow-auto">
            <div class="flex items-center justify-between">
                <div class="text-xs uppercase tracking-wide text-gray-400">New Order</div>
                <label class="flex items-center gap-1 text-[11px] text-gray-400 cursor-pointer select-none">
                    <input id="one-click" type="checkbox" checked class="rounded border-edge bg-panel2">
                    One-click
                </label>
            </div>

            <div class="bg-panel2 border border-edge rounded-lg p-3">
                <div class="text-lg font-bold text-white" id="order-symbol">—</div>
                <div class="text-xs text-gray-400" id="order-desc"></div>
                <div class="flex justify-between mt-3 text-center">
                    <div class="flex-1">
                        <div class="text-[10px] text-gray-500 uppercase">Sell · Bid</div>
                        <div class="text-xl font-bold text-down tabular-nums" id="order-bid">—</div>
                    </div>
                    <div class="flex-1">
                        <div class="text-[10px] text-gray-500 uppercase">Buy · Ask</div>
                        <div class="text-xl font-bold text-up tabular-nums" id="order-ask">—</div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Order type</label>
                <select id="order-type"
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent">
                    <option value="market">Market Execution</option>
                    <option value="buy_limit">Buy Limit</option>
                    <option value="sell_limit">Sell Limit</option>
                    <option value="buy_stop">Buy Stop</option>
                    <option value="sell_stop">Sell Stop</option>
                </select>
            </div>

            <div id="price-wrap" class="hidden">
                <label class="block text-xs text-gray-400 mb-1">Pending price</label>
                <input id="order-price" type="number" step="0.00001" placeholder="trigger price"
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
            </div>

            <div>
                <label class="block text-xs text-gray-400 mb-1">Volume (lots)</label>
                <input id="order-volume" type="number" min="0.01" max="100" step="0.01" value="0.10"
                    class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Stop Loss</label>
                    <input id="order-sl" type="number" step="0.00001" placeholder="optional"
                        class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Take Profit</label>
                    <input id="order-tp" type="number" step="0.00001" placeholder="optional"
                        class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
                </div>
            </div>

            <div id="market-actions" class="grid grid-cols-2 gap-2 mt-1">
                <button id="btn-sell" class="bg-down hover:brightness-110 text-white font-semibold rounded-md py-2.5 text-sm transition">SELL</button>
                <button id="btn-buy" class="bg-up hover:brightness-110 text-white font-semibold rounded-md py-2.5 text-sm transition">BUY</button>
            </div>
            <button id="btn-pending" class="hidden bg-accent hover:bg-blue-600 text-white font-semibold rounded-md py-2.5 text-sm transition mt-1">
                Place Pending Order
            </button>

            <div id="order-msg" class="text-xs min-h-[1rem]"></div>
        </section>

        {{-- Open positions + pending orders --}}
        <section class="col-span-12 lg:col-span-6 bg-panel flex flex-col overflow-hidden">
            <div class="px-3 py-2 text-xs uppercase tracking-wide text-gray-400 border-b border-edge flex justify-between">
                <span>Open Positions</span>
                <span id="positions-count" class="text-gray-500"></span>
            </div>
            <div class="overflow-auto flex-1">
                <table class="w-full text-xs">
                    <thead class="text-gray-500 sticky top-0 bg-panel">
                        <tr>
                            <th class="text-left px-3 py-1.5 font-medium">Ticket</th>
                            <th class="text-left px-2 py-1.5 font-medium">Symbol</th>
                            <th class="text-left px-2 py-1.5 font-medium">Type</th>
                            <th class="text-right px-2 py-1.5 font-medium">Volume</th>
                            <th class="text-right px-2 py-1.5 font-medium">Open</th>
                            <th class="text-right px-2 py-1.5 font-medium">Current</th>
                            <th class="text-right px-2 py-1.5 font-medium">S/L</th>
                            <th class="text-right px-2 py-1.5 font-medium">T/P</th>
                            <th class="text-right px-2 py-1.5 font-medium">Profit</th>
                            <th class="px-3 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody id="positions">
                        <tr><td colspan="10" class="px-3 py-4 text-center text-gray-500">No open positions</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="px-3 py-2 text-xs uppercase tracking-wide text-gray-400 border-y border-edge flex justify-between">
                <span>Pending Orders</span>
                <span id="orders-count" class="text-gray-500"></span>
            </div>
            <div class="overflow-auto max-h-44">
                <table class="w-full text-xs">
                    <thead class="text-gray-500 sticky top-0 bg-panel">
                        <tr>
                            <th class="text-left px-3 py-1.5 font-medium">Ticket</th>
                            <th class="text-left px-2 py-1.5 font-medium">Symbol</th>
                            <th class="text-left px-2 py-1.5 font-medium">Type</th>
                            <th class="text-right px-2 py-1.5 font-medium">Volume</th>
                            <th class="text-right px-2 py-1.5 font-medium">Price</th>
                            <th class="text-right px-2 py-1.5 font-medium">Market</th>
                            <th class="px-3 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody id="orders">
                        <tr><td colspan="7" class="px-3 py-3 text-center text-gray-500">No pending orders</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Right-click context menu for Market Watch --}}
    <div id="mw-menu" class="hidden fixed z-50 bg-panel2 border border-edge rounded-md shadow-lg text-xs py-1 w-40">
        <button data-menu="new-order" class="block w-full text-left px-3 py-1.5 hover:bg-panel">New Order</button>
        <button data-menu="specification" class="block w-full text-left px-3 py-1.5 hover:bg-panel">Specification</button>
        <button data-menu="tickchart" class="block w-full text-left px-3 py-1.5 hover:bg-panel">Tick Chart</button>
    </div>

    {{-- Specification modal --}}
    <div id="spec-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-panel border border-edge rounded-xl w-full max-w-md max-h-[85vh] overflow-auto">
            <div class="flex items-center justify-between px-4 py-3 border-b border-edge">
                <div>
                    <div class="font-semibold text-white" id="spec-symbol">—</div>
                    <div class="text-xs text-gray-400" id="spec-desc"></div>
                </div>
                <button id="spec-close" class="text-gray-400 hover:text-white text-lg leading-none">✕</button>
            </div>
            <table class="w-full text-xs" id="spec-table"></table>
        </div>
    </div>

    {{-- Modify SL/TP modal --}}
    <div id="modify-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-panel border border-edge rounded-xl w-full max-w-xs">
            <div class="flex items-center justify-between px-4 py-3 border-b border-edge">
                <div class="font-semibold text-white text-sm">Modify <span id="modify-ticket"></span></div>
                <button id="modify-close" class="text-gray-400 hover:text-white text-lg leading-none">✕</button>
            </div>
            <div class="p-4 space-y-3">
                <div class="text-xs text-gray-400" id="modify-info"></div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Stop Loss</label>
                    <input id="modify-sl" type="number" step="0.00001" placeholder="none"
                        class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Take Profit</label>
                    <input id="modify-tp" type="number" step="0.00001" placeholder="none"
                        class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
                </div>
                <div id="modify-msg" class="text-xs min-h-[1rem] text-down"></div>
                <button id="modify-save" class="w-full bg-accent hover:bg-blue-600 text-white font-semibold rounded-md py-2 text-sm">Save</button>
            </div>
        </div>
    </div>
    @endif
</div>

@if ($account)
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const fmt = (n, d = 2) => n === null || n === undefined ? '—' : Number(n).toFixed(d);
    const digitsBySymbol = {};
    document.querySelectorAll('#watchlist tr').forEach(r => digitsBySymbol[r.dataset.symbol] = +r.dataset.digits);

    let selected = document.querySelector('#watchlist tr')?.dataset.symbol || null;
    let lastQuotes = {};
    let tickTab = false;
    let tickData = [];   // recent mid-prices for the selected symbol (tick chart)

    async function api(url, opts = {}) {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(opts.body ? { 'Content-Type': 'application/json' } : {}) },
            ...opts,
        });
        const json = await res.json().catch(() => ({}));
        return { ok: res.ok, json };
    }

    function selectSymbol(sym) {
        selected = sym;
        document.querySelectorAll('#watchlist tr').forEach(r =>
            r.classList.toggle('bg-panel2', r.dataset.symbol === sym));
        renderOrderPanel();
        tickData = [];
        if (tickTab) loadTicks();
    }

    function renderOrderPanel() {
        const q = lastQuotes[selected];
        document.getElementById('order-symbol').textContent = selected || '—';
        document.getElementById('order-desc').textContent = q?.description || '';
        const d = digitsBySymbol[selected] ?? 5;
        document.getElementById('order-bid').textContent = q ? fmt(q.bid, d) : '—';
        document.getElementById('order-ask').textContent = q ? fmt(q.ask, d) : '—';
    }

    async function loadQuotes() {
        const { ok, json } = await api('{{ route('api.quotes') }}');
        if (!ok) return;
        for (const q of json.data) {
            const d = digitsBySymbol[q.symbol] ?? 5;
            const row = document.querySelector(`#watchlist tr[data-symbol="${q.symbol}"]`);
            if (row) {
                const prevBid = lastQuotes[q.symbol]?.bid;
                updateCell(row.querySelector('[data-field="bid"]'), q.bid, prevBid, d);
                updateCell(row.querySelector('[data-field="ask"]'), q.ask, lastQuotes[q.symbol]?.ask, d);
                renderChange(row.querySelector('[data-field="change"]'), q.daily_change);
                renderArrow(row.querySelector('[data-field="arrow"]'), q.bid, prevBid, q.daily_change);
            }
            const wasKnown = lastQuotes[q.symbol] !== undefined;
            lastQuotes[q.symbol] = q;
            // Feed the live tick chart for the selected symbol.
            if (q.symbol === selected && wasKnown && q.bid != null) appendLiveTick(q);
        }
        renderOrderPanel();
    }

    function renderChange(cell, change) {
        if (!cell) return;
        if (change === null || change === undefined) { cell.textContent = '—'; return; }
        cell.textContent = (change > 0 ? '+' : '') + change.toFixed(2) + '%';
        cell.classList.remove('text-up', 'text-down', 'text-gray-500');
        cell.classList.add(change > 0 ? 'text-up' : change < 0 ? 'text-down' : 'text-gray-500');
    }

    function renderArrow(cell, bid, prevBid, change) {
        if (!cell) return;
        let dir = 0;
        if (prevBid !== undefined && bid !== prevBid) dir = bid > prevBid ? 1 : -1;
        else if (change != null) dir = change > 0 ? 1 : change < 0 ? -1 : 0;
        cell.textContent = dir > 0 ? '▲' : dir < 0 ? '▼' : '●';
        cell.classList.remove('text-up', 'text-down', 'text-gray-600');
        cell.classList.add(dir > 0 ? 'text-up' : dir < 0 ? 'text-down' : 'text-gray-600');
    }

    function updateCell(cell, value, prev, d) {
        if (!cell || value === null) return;
        cell.textContent = fmt(value, d);
        if (prev !== undefined && value !== prev) {
            cell.classList.remove('text-up', 'text-down');
            cell.classList.add(value > prev ? 'text-up' : 'text-down');
        }
    }

    async function loadAccount() {
        const { ok, json } = await api('{{ route('api.account') }}');
        if (!ok) return;
        const m = json.data.metrics;
        const set = (k, v, color = false) => {
            const el = document.querySelector(`[data-acc="${k}"]`);
            if (!el) return;
            el.textContent = v;
            if (color) {
                el.classList.remove('text-up', 'text-down', 'text-white');
                el.classList.add(parseFloat(v) > 0 ? 'text-up' : parseFloat(v) < 0 ? 'text-down' : 'text-white');
            }
        };
        set('balance', fmt(m.balance));
        set('equity', fmt(m.equity));
        set('used_margin', fmt(m.used_margin));
        set('free_margin', fmt(m.free_margin));
        set('margin_level', m.margin_level === null ? '—' : fmt(m.margin_level) + '%');
    }

    async function loadPositions() {
        const { ok, json } = await api('{{ route('api.positions') }}');
        if (!ok) return;
        const tbody = document.getElementById('positions');
        const rows = json.data;
        document.getElementById('positions-count').textContent = rows.length ? `${rows.length} open` : '';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="px-3 py-4 text-center text-gray-500">No open positions</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(p => {
            const d = digitsBySymbol[p.symbol] ?? 5;
            const pc = p.profit > 0 ? 'text-up' : p.profit < 0 ? 'text-down' : 'text-gray-300';
            const sc = p.side === 'buy' ? 'text-up' : 'text-down';
            const sl = p.stop_loss ? fmt(p.stop_loss, d) : '—';
            const tp = p.take_profit ? fmt(p.take_profit, d) : '—';
            const meta = encodeURIComponent(JSON.stringify({ id: p.id, ticket: p.ticket, symbol: p.symbol, side: p.side, sl: p.stop_loss, tp: p.take_profit, digits: d }));
            return `<tr class="border-t border-edge/50">
                <td class="px-3 py-1.5 tabular-nums text-gray-400">${p.ticket}</td>
                <td class="px-2 py-1.5 font-medium">${p.symbol}</td>
                <td class="px-2 py-1.5 uppercase ${sc}">${p.side}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.volume)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.open_price, d)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.current_price, d)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums text-gray-400">${sl}</td>
                <td class="px-2 py-1.5 text-right tabular-nums text-gray-400">${tp}</td>
                <td class="px-2 py-1.5 text-right tabular-nums ${pc}">${fmt(p.profit)}</td>
                <td class="px-3 py-1.5 text-right whitespace-nowrap">
                    <button data-modify="${meta}" class="text-gray-400 hover:text-accent mr-2" title="Modify S/L · T/P">✎</button>
                    <button data-close="${p.id}" class="text-gray-400 hover:text-down" title="Close">✕</button>
                </td>
            </tr>`;
        }).join('');
    }

    async function loadOrders() {
        const { ok, json } = await api('{{ route('api.orders.index') }}');
        if (!ok) return;
        const tbody = document.getElementById('orders');
        const rows = json.data;
        document.getElementById('orders-count').textContent = rows.length ? `${rows.length} pending` : '';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-3 text-center text-gray-500">No pending orders</td></tr>';
            return;
        }
        const label = { buy_limit: 'Buy Limit', sell_limit: 'Sell Limit', buy_stop: 'Buy Stop', sell_stop: 'Sell Stop' };
        tbody.innerHTML = rows.map(o => {
            const d = digitsBySymbol[o.symbol] ?? 5;
            const sc = o.side === 'buy' ? 'text-up' : 'text-down';
            return `<tr class="border-t border-edge/50">
                <td class="px-3 py-1.5 tabular-nums text-gray-400">${o.ticket}</td>
                <td class="px-2 py-1.5 font-medium">${o.symbol}</td>
                <td class="px-2 py-1.5 ${sc}">${label[o.type] || o.type}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(o.volume)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(o.price, d)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums text-gray-400">${o.market_price != null ? fmt(o.market_price, d) : '—'}</td>
                <td class="px-3 py-1.5 text-right">
                    <button data-cancel="${o.id}" class="text-gray-400 hover:text-down" title="Cancel">✕</button>
                </td>
            </tr>`;
        }).join('');
    }

    async function cancelOrder(id) {
        const { ok, json } = await api(`/api/orders/${id}/cancel`, { method: 'POST' });
        const msg = document.getElementById('order-msg');
        msg.className = 'text-xs min-h-[1rem] ' + (ok ? 'text-gray-300' : 'text-down');
        msg.textContent = ok ? `Cancelled pending #${json.data.ticket}` : (json.error?.message || 'Cancel failed.');
        loadOrders();
    }

    // Market order (one-click or confirmed). `side` = buy|sell.
    async function placeMarket(side) {
        const volume = parseFloat(document.getElementById('order-volume').value);
        if (!document.getElementById('one-click').checked) {
            if (!confirm(`${side.toUpperCase()} ${volume} ${selected} at market?`)) return;
        }
        await submitOrder({
            type: side, symbol: selected, volume,
            stop_loss: parseFloat(document.getElementById('order-sl').value) || null,
            take_profit: parseFloat(document.getElementById('order-tp').value) || null,
        });
    }

    // Pending order using the selected order type + trigger price.
    async function placePending() {
        await submitOrder({
            type: document.getElementById('order-type').value,
            symbol: selected,
            volume: parseFloat(document.getElementById('order-volume').value),
            price: parseFloat(document.getElementById('order-price').value) || null,
            stop_loss: parseFloat(document.getElementById('order-sl').value) || null,
            take_profit: parseFloat(document.getElementById('order-tp').value) || null,
        });
    }

    async function submitOrder(body) {
        const msg = document.getElementById('order-msg');
        msg.className = 'text-xs min-h-[1rem] text-gray-400';
        msg.textContent = 'Placing order…';
        const { ok, json } = await api('{{ route('api.orders.store') }}', { method: 'POST', body: JSON.stringify(body) });
        if (ok) {
            msg.className = 'text-xs min-h-[1rem] text-up';
            msg.textContent = json.data.kind === 'pending'
                ? `${json.data.type.replace('_', ' ')} ${json.data.volume} ${json.data.symbol} @ ${json.data.price} placed (#${json.data.ticket})`
                : `${json.data.side.toUpperCase()} ${json.data.volume} ${json.data.symbol} filled @ ${json.data.open_price} (#${json.data.ticket})`;
            await Promise.all([loadPositions(), loadOrders(), loadAccount()]);
        } else {
            msg.className = 'text-xs min-h-[1rem] text-down';
            msg.textContent = json.error?.message || (json.errors ? Object.values(json.errors)[0][0] : 'Order failed.');
        }
    }

    // Toggle order-type UI between market and pending modes.
    function onOrderTypeChange() {
        const pending = document.getElementById('order-type').value !== 'market';
        document.getElementById('price-wrap').classList.toggle('hidden', !pending);
        document.getElementById('market-actions').classList.toggle('hidden', pending);
        document.getElementById('btn-pending').classList.toggle('hidden', !pending);
        // Prefill a sensible trigger price from the current quote.
        const q = lastQuotes[selected];
        const priceEl = document.getElementById('order-price');
        if (pending && q && !priceEl.value) {
            priceEl.value = (document.getElementById('order-type').value.startsWith('buy') ? q.ask : q.bid).toFixed(digitsBySymbol[selected] ?? 5);
        }
    }

    // ---- Modify SL/TP modal ----
    let modifyId = null;
    function openModify(meta) {
        const m = JSON.parse(decodeURIComponent(meta));
        modifyId = m.id;
        document.getElementById('modify-ticket').textContent = '#' + m.ticket;
        document.getElementById('modify-info').textContent = `${m.symbol} ${m.side.toUpperCase()}`;
        document.getElementById('modify-sl').value = m.sl ?? '';
        document.getElementById('modify-tp').value = m.tp ?? '';
        document.getElementById('modify-msg').textContent = '';
        document.getElementById('modify-modal').classList.remove('hidden');
    }
    document.getElementById('modify-close').addEventListener('click', () =>
        document.getElementById('modify-modal').classList.add('hidden'));
    document.getElementById('modify-modal').addEventListener('click', e => {
        if (e.target.id === 'modify-modal') e.target.classList.add('hidden');
    });
    document.getElementById('modify-save').addEventListener('click', async () => {
        if (!modifyId) return;
        const body = {
            stop_loss: parseFloat(document.getElementById('modify-sl').value) || null,
            take_profit: parseFloat(document.getElementById('modify-tp').value) || null,
        };
        const { ok, json } = await api(`/api/positions/${modifyId}/modify`, { method: 'POST', body: JSON.stringify(body) });
        if (ok) {
            document.getElementById('modify-modal').classList.add('hidden');
            await Promise.all([loadPositions(), loadAccount()]);
        } else {
            document.getElementById('modify-msg').textContent = json.error?.message || 'Modify failed.';
        }
    });

    async function closePosition(id) {
        const { ok, json } = await api(`/api/positions/${id}/close`, { method: 'POST' });
        const msg = document.getElementById('order-msg');
        if (ok) {
            msg.className = 'text-xs min-h-[1rem] text-gray-300';
            msg.textContent = `Closed #${json.data.ticket} @ ${json.data.close_price} · P/L ${fmt(json.data.profit)}`;
        } else {
            msg.className = 'text-xs min-h-[1rem] text-down';
            msg.textContent = json.error?.message || 'Close failed.';
        }
        await Promise.all([loadPositions(), loadAccount()]);
    }

    // ---- Market Watch: symbol search ----
    document.getElementById('mw-search').addEventListener('input', e => {
        const term = e.target.value.trim().toUpperCase();
        let shown = 0;
        document.querySelectorAll('#watchlist tr').forEach(r => {
            const match = r.dataset.symbol.includes(term);
            r.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        document.getElementById('mw-count').textContent = shown + ' symbols';
    });

    // ---- Market Watch: Symbols / Tick Chart tabs ----
    function setMwTab(tab) {
        tickTab = tab === 'tickchart';
        document.getElementById('mw-symbols').classList.toggle('hidden', tickTab);
        const tc = document.getElementById('mw-tickchart');
        tc.classList.toggle('hidden', !tickTab);
        tc.classList.toggle('flex', tickTab);
        document.querySelectorAll('.mw-tab').forEach(b => {
            const active = b.dataset.mwtab === tab;
            b.classList.toggle('text-accent', active);
            b.classList.toggle('border-accent', active);
            b.classList.toggle('bg-panel2', active);
            b.classList.toggle('text-gray-400', !active);
            b.classList.toggle('border-transparent', !active);
        });
        if (tickTab) loadTicks();
    }
    document.querySelectorAll('.mw-tab').forEach(b =>
        b.addEventListener('click', () => setMwTab(b.dataset.mwtab)));

    async function loadTicks() {
        if (!selected) return;
        document.getElementById('tick-title').textContent = selected + ' · tick chart';
        const { ok, json } = await api(`/api/instruments/${selected}/ticks?limit=200`);
        if (!ok) return;
        tickData = json.data.ticks.map(t => t.mid);
        drawTicks(digitsBySymbol[selected] ?? 5);
    }

    function appendLiveTick(q) {
        tickData.push((q.bid + q.ask) / 2);
        if (tickData.length > 300) tickData.shift();
        if (tickTab) drawTicks(digitsBySymbol[selected] ?? 5);
    }

    function drawTicks(d) {
        const canvas = document.getElementById('tick-canvas');
        const dpr = window.devicePixelRatio || 1;
        const w = canvas.clientWidth, h = canvas.clientHeight;
        if (!w || !h) return;
        canvas.width = w * dpr; canvas.height = h * dpr;
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, w, h);
        if (tickData.length < 2) return;
        const min = Math.min(...tickData), max = Math.max(...tickData);
        const range = (max - min) || 1, pad = 6;
        const x = i => pad + (i / (tickData.length - 1)) * (w - 2 * pad);
        const y = v => h - pad - ((v - min) / range) * (h - 2 * pad);
        ctx.beginPath();
        tickData.forEach((v, i) => i === 0 ? ctx.moveTo(x(i), y(v)) : ctx.lineTo(x(i), y(v)));
        ctx.strokeStyle = '#3b82f6'; ctx.lineWidth = 1.5; ctx.stroke();
        const last = tickData[tickData.length - 1];
        ctx.fillStyle = last >= tickData[0] ? '#26a69a' : '#ef5350';
        ctx.beginPath(); ctx.arc(x(tickData.length - 1), y(last), 2.5, 0, Math.PI * 2); ctx.fill();
        document.getElementById('tick-min').textContent = min.toFixed(d);
        document.getElementById('tick-max').textContent = max.toFixed(d);
        document.getElementById('tick-last').textContent = last.toFixed(d);
    }

    // ---- Market Watch: right-click context menu ----
    let menuSymbol = null;
    const menu = document.getElementById('mw-menu');
    document.getElementById('watchlist').addEventListener('contextmenu', e => {
        const row = e.target.closest('tr[data-symbol]');
        if (!row) return;
        e.preventDefault();
        menuSymbol = row.dataset.symbol;
        menu.style.left = Math.min(e.clientX, window.innerWidth - 170) + 'px';
        menu.style.top = Math.min(e.clientY, window.innerHeight - 110) + 'px';
        menu.classList.remove('hidden');
    });
    document.addEventListener('click', () => menu.classList.add('hidden'));
    menu.addEventListener('click', e => {
        const act = e.target.dataset.menu;
        if (!act || !menuSymbol) return;
        if (act === 'new-order') selectSymbol(menuSymbol);
        else if (act === 'specification') openSpec(menuSymbol);
        else if (act === 'tickchart') { selectSymbol(menuSymbol); setMwTab('tickchart'); }
    });

    // ---- Specification modal ----
    async function openSpec(sym) {
        const { ok, json } = await api(`/api/instruments/${sym}/specification`);
        if (!ok) return;
        const s = json.data;
        document.getElementById('spec-symbol').textContent = s.symbol;
        document.getElementById('spec-desc').textContent = s.description;
        const rows = [
            ['Category', s.category],
            ['Digits', s.digits],
            ['Contract size', s.contract_size.toLocaleString()],
            ['Spread (points)', s.spread ?? '—'],
            ['Stops level (points)', s.stops_level],
            ['Volume min / step / max', `${s.volume_min} / ${s.volume_step} / ${s.volume_max}`],
            ['Swap long', s.swap_long],
            ['Swap short', s.swap_short],
            ['Margin / lot', s.margin_per_lot != null ? `${s.margin_per_lot.toLocaleString()} ${s.margin_currency}` : '—'],
            ['Leverage', '1:' + s.leverage],
        ];
        document.getElementById('spec-table').innerHTML = rows.map(([k, v]) =>
            `<tr class="border-t border-edge/50"><td class="px-4 py-1.5 text-gray-400">${k}</td><td class="px-4 py-1.5 text-right text-gray-200 tabular-nums">${v}</td></tr>`
        ).join('');
        document.getElementById('spec-modal').classList.remove('hidden');
    }
    document.getElementById('spec-close').addEventListener('click', () =>
        document.getElementById('spec-modal').classList.add('hidden'));
    document.getElementById('spec-modal').addEventListener('click', e => {
        if (e.target.id === 'spec-modal') e.target.classList.add('hidden');
    });

    // Event wiring
    document.getElementById('watchlist').addEventListener('click', e => {
        const row = e.target.closest('tr[data-symbol]');
        if (row) selectSymbol(row.dataset.symbol);
    });
    document.getElementById('positions').addEventListener('click', e => {
        const closeBtn = e.target.closest('[data-close]');
        if (closeBtn) { closePosition(closeBtn.dataset.close); return; }
        const modBtn = e.target.closest('[data-modify]');
        if (modBtn) openModify(modBtn.dataset.modify);
    });
    document.getElementById('orders').addEventListener('click', e => {
        const btn = e.target.closest('[data-cancel]');
        if (btn) cancelOrder(btn.dataset.cancel);
    });
    document.getElementById('btn-buy').addEventListener('click', () => placeMarket('buy'));
    document.getElementById('btn-sell').addEventListener('click', () => placeMarket('sell'));
    document.getElementById('btn-pending').addEventListener('click', placePending);
    document.getElementById('order-type').addEventListener('change', onOrderTypeChange);

    // Initial load + polling
    if (selected) selectSymbol(selected);
    loadQuotes(); loadAccount(); loadPositions(); loadOrders();
    setInterval(() => { loadQuotes(); loadPositions(); loadOrders(); }, 1500);
    setInterval(loadAccount, 2000);
})();
</script>
@endif
@endsection
