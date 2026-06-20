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
            @if ($account)
                <button id="funds-btn" class="text-gray-300 hover:text-white">Funds</button>
            @endif
            <a href="{{ route('history') }}" class="text-gray-300 hover:text-white">History</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-gray-300 hover:text-white">Logout</button>
            </form>
        </div>
    </header>

    @if (! $account)
        <div class="p-6 text-sm text-down">No trading account is provisioned for your user.</div>
    @else
    <div class="flex-1 flex flex-col overflow-hidden">
    {{-- Chart toolbar --}}
    <div class="bg-panel border-b border-edge px-3 py-1.5 flex items-center gap-2 text-xs flex-wrap shrink-0">
        <span class="font-semibold text-white" id="chart-symbol-label">—</span>
        <span class="text-gray-500" id="chart-tf-label"></span>
        <div class="w-px h-4 bg-edge mx-1"></div>
        <div class="flex items-center gap-1" id="chart-types">
            <button data-ctype="candle_solid" class="ctype-btn px-2 py-1 rounded text-accent bg-panel2">Candles</button>
            <button data-ctype="ohlc" class="ctype-btn px-2 py-1 rounded text-gray-400 hover:text-gray-200">Bars</button>
            <button data-ctype="area" class="ctype-btn px-2 py-1 rounded text-gray-400 hover:text-gray-200">Line</button>
        </div>
        <div class="w-px h-4 bg-edge mx-1"></div>
        <div class="flex items-center gap-0.5" id="chart-tfs">
            @foreach (['M1','M5','M15','M30','H1','H4','D1','W1','MN'] as $tf)
            <button data-tf="{{ $tf }}" class="tf-btn px-2 py-1 rounded font-medium {{ $tf === 'M5' ? 'text-accent bg-panel2' : 'text-gray-400 hover:text-gray-200' }}">{{ $tf }}</button>
            @endforeach
        </div>
        <div class="w-px h-4 bg-edge mx-1"></div>
        <div class="relative">
            <button id="ind-btn" class="px-2 py-1 rounded text-gray-300 hover:text-white hover:bg-panel2">Indicators ▾</button>
            <div id="ind-menu" class="hidden absolute z-40 mt-1 left-0 top-full bg-panel2 border border-edge rounded-md shadow-lg w-60 max-h-[72vh] overflow-auto"></div>
        </div>
    </div>

    <div class="flex-1 grid grid-cols-12 gap-px bg-edge overflow-hidden min-h-0">
        {{-- Order ticket (left) --}}
        <section class="col-span-12 lg:col-span-3 bg-panel p-4 flex flex-col gap-3 overflow-auto">
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

        {{-- Chart (center) --}}
        <section class="col-span-12 lg:col-span-6 bg-panel flex overflow-hidden">
            <div id="draw-toolbar" class="w-9 shrink-0 border-r border-edge flex flex-col items-center py-1 gap-0.5 overflow-auto text-sm"></div>
            <div id="chart" class="flex-1 min-w-0 min-h-0"></div>
        </section>

        {{-- Market Watch (right) --}}
        <section class="col-span-12 md:col-span-6 lg:col-span-3 bg-panel flex flex-col overflow-hidden">
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
    </div>

    {{-- Toolbox: Trade / History / Journal --}}
    <div class="flex flex-col shrink-0 border-t border-edge bg-panel" style="height:15rem">
        <div class="flex items-center gap-1 px-2 border-b border-edge text-xs">
            <button data-tbtab="trade" class="tb-tab px-3 py-1.5 border-b-2 border-accent text-accent font-medium">Trade</button>
            <button data-tbtab="history" class="tb-tab px-3 py-1.5 border-b-2 border-transparent text-gray-400 hover:text-gray-200">History</button>
            <button data-tbtab="journal" class="tb-tab px-3 py-1.5 border-b-2 border-transparent text-gray-400 hover:text-gray-200">Journal</button>
            <div class="ml-auto text-[11px] text-gray-500 tabular-nums" id="tb-trade-summary"></div>
        </div>

        {{-- Trade --}}
        <div id="tb-trade" class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-px bg-edge overflow-hidden">
            <div class="bg-panel flex flex-col overflow-hidden">
                <div class="px-3 py-1.5 text-xs uppercase tracking-wide text-gray-400 border-b border-edge flex justify-between">
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
            </div>

            <div class="bg-panel flex flex-col overflow-hidden">
                <div class="px-3 py-1.5 text-xs uppercase tracking-wide text-gray-400 border-b border-edge flex justify-between">
                    <span>Pending Orders</span>
                    <span id="orders-count" class="text-gray-500"></span>
                </div>
                <div class="overflow-auto flex-1">
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
            </div>
        </div>

        {{-- History --}}
        <div id="tb-history" class="hidden flex-1 flex-col overflow-hidden">
            <div id="history-summary" class="flex flex-wrap gap-x-5 gap-y-1 px-3 py-1.5 border-b border-edge text-[11px] text-gray-400">
                <span>Loading…</span>
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
                            <th class="text-right px-2 py-1.5 font-medium">Close</th>
                            <th class="text-left px-2 py-1.5 font-medium">Closed</th>
                            <th class="text-right px-3 py-1.5 font-medium">Profit</th>
                        </tr>
                    </thead>
                    <tbody id="history-rows">
                        <tr><td colspan="8" class="px-3 py-4 text-center text-gray-500">No history</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Journal --}}
        <div id="tb-journal" class="hidden flex-1 overflow-auto p-2 text-[11px] font-mono leading-relaxed text-gray-300"></div>
    </div>
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

    {{-- Deposit / Withdraw modal --}}
    <div id="funds-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
        <div class="bg-panel border border-edge rounded-xl w-full max-w-xs">
            <div class="flex items-center justify-between px-4 py-3 border-b border-edge">
                <div class="font-semibold text-white text-sm">Deposit / Withdraw</div>
                <button id="funds-close" class="text-gray-400 hover:text-white text-lg leading-none">✕</button>
            </div>
            <div class="p-4 space-y-3">
                <div class="text-xs text-gray-400">Balance: <span class="text-gray-200" id="funds-balance">—</span></div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Amount ({{ $account->currency ?? 'USD' }})</label>
                    <input id="funds-amount" type="number" min="0.01" step="0.01" placeholder="0.00"
                        class="w-full bg-panel2 border border-edge rounded-md px-3 py-2 text-sm focus:outline-none focus:border-accent tabular-nums">
                </div>
                <div id="funds-msg" class="text-xs min-h-[1rem]"></div>
                <div class="grid grid-cols-2 gap-2">
                    <button id="funds-withdraw" class="bg-down hover:brightness-110 text-white font-semibold rounded-md py-2 text-sm">Withdraw</button>
                    <button id="funds-deposit" class="bg-up hover:brightness-110 text-white font-semibold rounded-md py-2 text-sm">Deposit</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@if ($account)
<script src="/vendor/klinecharts.min.js"></script>
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
    let timeframe = 'M5';
    let chartType = 'candle_solid';
    let chart = null;
    let chartDigits = 5;
    let online = true;

    async function api(url, opts = {}) {
        try {
            const res = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(opts.body ? { 'Content-Type': 'application/json' } : {}) },
                ...opts,
            });
            if (!online) { online = true; journal('Connection restored', 'success'); }
            const json = await res.json().catch(() => ({}));
            return { ok: res.ok, json };
        } catch (e) {
            if (online) { online = false; journal('Connection lost — retrying', 'error'); }
            return { ok: false, json: {} };
        }
    }

    function selectSymbol(sym) {
        selected = sym;
        document.querySelectorAll('#watchlist tr').forEach(r =>
            r.classList.toggle('bg-panel2', r.dataset.symbol === sym));
        renderOrderPanel();
        tickData = [];
        if (tickTab) loadTicks();
        loadChart();
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
            // Feed the live tick chart + candle for the selected symbol.
            if (q.symbol === selected && wasKnown && q.bid != null) { appendLiveTick(q); updateLatestCandle(q); }
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
        const tb = document.getElementById('tb-trade-summary');
        if (tb) tb.textContent = `Balance ${fmt(m.balance)} · Equity ${fmt(m.equity)} · Free ${fmt(m.free_margin)}`;
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
        loadOrders(); if (ok) loadJournal();
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
            const text = json.data.kind === 'pending'
                ? `${json.data.type.replace('_', ' ')} ${json.data.volume} ${json.data.symbol} @ ${json.data.price} placed (#${json.data.ticket})`
                : `${json.data.side.toUpperCase()} ${json.data.volume} ${json.data.symbol} filled @ ${json.data.open_price} (#${json.data.ticket})`;
            msg.textContent = text;
            await Promise.all([loadPositions(), loadOrders(), loadAccount(), loadJournal()]);
        } else {
            msg.className = 'text-xs min-h-[1rem] text-down';
            const err = json.error?.message || (json.errors ? Object.values(json.errors)[0][0] : 'Order failed.');
            msg.textContent = err;
            journal('Order rejected: ' + err, 'error');
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
            await Promise.all([loadPositions(), loadAccount(), loadJournal()]);
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
        await Promise.all([loadPositions(), loadAccount(), loadJournal()]);
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

    // ---- Charting (KLineCharts) ----
    function initChart() {
        registerCustomIndicators();
        registerCustomOverlays();
        chart = klinecharts.init('chart');
        window.__chart = chart;
        chart.setStyles({
            grid: { horizontal: { color: '#2a323d' }, vertical: { color: '#2a323d' } },
            candle: {
                type: chartType,
                bar: { upColor: '#26a69a', downColor: '#ef5350', upBorderColor: '#26a69a', downBorderColor: '#ef5350', upWickColor: '#26a69a', downWickColor: '#ef5350' },
                priceMark: { last: { line: { color: '#3b82f6' } } },
                tooltip: { rect: { color: '#1c232c' }, text: { color: '#cbd5e1' } },
            },
            xAxis: { axisLine: { color: '#2a323d' }, tickText: { color: '#94a3b8' } },
            yAxis: { axisLine: { color: '#2a323d' }, tickText: { color: '#94a3b8' } },
            crosshair: { horizontal: { text: { backgroundColor: '#3b82f6' } }, vertical: { text: { backgroundColor: '#3b82f6' } } },
        });
        window.addEventListener('resize', () => chart && chart.resize());
    }

    async function loadChart() {
        if (!chart || !selected) return;
        chartDigits = digitsBySymbol[selected] ?? 5;
        chart.setPriceVolumePrecision(chartDigits, 0);
        document.getElementById('chart-symbol-label').textContent = selected;
        document.getElementById('chart-tf-label').textContent = timeframe;
        const { ok, json } = await api(`/api/instruments/${selected}/candles?timeframe=${timeframe}&limit=300`);
        if (!ok) return;
        chart.applyNewData(json.data.candles);
    }

    function updateLatestCandle(q) {
        if (!chart || !q || q.bid == null) return;
        const mid = (q.bid + q.ask) / 2;
        const list = chart.getDataList();
        const last = list[list.length - 1];
        if (!last) return;
        const tfMs = { M1: 60, M5: 300, M15: 900, M30: 1800, H1: 3600, H4: 14400, D1: 86400, W1: 604800, MN: 2592000 }[timeframe] * 1000;
        const now = Date.now();
        if (now < last.timestamp + tfMs) {
            // Same bucket: update the forming candle.
            chart.updateData({ timestamp: last.timestamp, open: last.open, high: Math.max(last.high, mid), low: Math.min(last.low, mid), close: mid, volume: (last.volume || 0) + 1 });
        } else {
            // New bucket: append a fresh candle.
            const start = Math.floor(now / tfMs) * tfMs;
            chart.updateData({ timestamp: start, open: mid, high: mid, low: mid, close: mid, volume: 1 });
        }
    }

    function setChartType(type) {
        chartType = type;
        chart && chart.setStyles({ candle: { type } });
        document.querySelectorAll('.ctype-btn').forEach(b => {
            const active = b.dataset.ctype === type;
            b.classList.toggle('text-accent', active);
            b.classList.toggle('bg-panel2', active);
            b.classList.toggle('text-gray-400', !active);
        });
    }

    function setTimeframe(tf) {
        timeframe = tf;
        document.querySelectorAll('.tf-btn').forEach(b => {
            const active = b.dataset.tf === tf;
            b.classList.toggle('text-accent', active);
            b.classList.toggle('bg-panel2', active);
            b.classList.toggle('text-gray-400', !active);
        });
        loadChart();
    }

    document.getElementById('chart-types').addEventListener('click', e => {
        const b = e.target.closest('[data-ctype]');
        if (b) setChartType(b.dataset.ctype);
    });
    document.getElementById('chart-tfs').addEventListener('click', e => {
        const b = e.target.closest('[data-tf]');
        if (b) setTimeframe(b.dataset.tf);
    });

    // ---- Technical indicators (KLineCharts built-ins + custom) ----
    // category: Trend | Oscillators | Volumes | Bill Williams; overlay = drawn on the price pane.
    const INDICATORS = [
        // Trend
        { name: 'MA', label: 'Moving Average', cat: 'Trend', overlay: true },
        { name: 'EMA', label: 'Exponential MA', cat: 'Trend', overlay: true },
        { name: 'SMA', label: 'Smoothed MA', cat: 'Trend', overlay: true },
        { name: 'BBI', label: 'Bull & Bear Index', cat: 'Trend', overlay: true },
        { name: 'BOLL', label: 'Bollinger Bands', cat: 'Trend', overlay: true },
        { name: 'SAR', label: 'Parabolic SAR', cat: 'Trend', overlay: true },
        { name: 'ICHIMOKU', label: 'Ichimoku Kinko Hyo', cat: 'Trend', overlay: true },
        { name: 'DMA', label: 'DMA', cat: 'Trend', overlay: false },
        { name: 'TRIX', label: 'TRIX', cat: 'Trend', overlay: false },
        { name: 'DMI', label: 'DMI / ADX', cat: 'Trend', overlay: false },
        // Oscillators
        { name: 'MACD', label: 'MACD', cat: 'Oscillators', overlay: false },
        { name: 'RSI', label: 'Relative Strength', cat: 'Oscillators', overlay: false },
        { name: 'KDJ', label: 'KDJ', cat: 'Oscillators', overlay: false },
        { name: 'STOCH', label: 'Stochastic', cat: 'Oscillators', overlay: false },
        { name: 'CCI', label: 'CCI', cat: 'Oscillators', overlay: false },
        { name: 'WR', label: 'Williams %R', cat: 'Oscillators', overlay: false },
        { name: 'BIAS', label: 'BIAS', cat: 'Oscillators', overlay: false },
        { name: 'BRAR', label: 'BRAR', cat: 'Oscillators', overlay: false },
        { name: 'CR', label: 'CR', cat: 'Oscillators', overlay: false },
        { name: 'PSY', label: 'PSY', cat: 'Oscillators', overlay: false },
        { name: 'ROC', label: 'Rate of Change', cat: 'Oscillators', overlay: false },
        { name: 'MTM', label: 'Momentum', cat: 'Oscillators', overlay: false },
        // Volumes
        { name: 'VOL', label: 'Volume', cat: 'Volumes', overlay: false },
        { name: 'OBV', label: 'On Balance Volume', cat: 'Volumes', overlay: false },
        { name: 'VR', label: 'Volume Ratio', cat: 'Volumes', overlay: false },
        { name: 'PVT', label: 'Price Volume Trend', cat: 'Volumes', overlay: false },
        { name: 'EMV', label: 'Ease of Movement', cat: 'Volumes', overlay: false },
        // Bill Williams
        { name: 'AO', label: 'Awesome Oscillator', cat: 'Bill Williams', overlay: false },
        { name: 'ALLIGATOR', label: 'Alligator', cat: 'Bill Williams', overlay: true },
        { name: 'BWMFI', label: 'Market Facilitation Index', cat: 'Bill Williams', overlay: false },
    ];
    const activeIndicators = {}; // name -> paneId

    function registerCustomIndicators() {
        if (!window.klinecharts || window.__indicatorsRegistered) return;
        window.__indicatorsRegistered = true;
        const reg = klinecharts.registerIndicator;

        // Stochastic Oscillator (%K / %D)
        reg({
            name: 'STOCH', shortName: 'STOCH', calcParams: [14, 3, 3],
            figures: [{ key: 'k', title: 'K: ', type: 'line' }, { key: 'd', title: 'D: ', type: 'line' }],
            calc: (data, { calcParams: [n, kP, dP] }) => {
                const ks = [], res = [];
                for (let i = 0; i < data.length; i++) {
                    let hh = -Infinity, ll = Infinity;
                    for (let j = Math.max(0, i - n + 1); j <= i; j++) { hh = Math.max(hh, data[j].high); ll = Math.min(ll, data[j].low); }
                    ks.push(hh === ll ? 0 : (data[i].close - ll) / (hh - ll) * 100);
                    const kS = ks.slice(Math.max(0, ks.length - kP));
                    res.push({ _k: kS.reduce((a, b) => a + b, 0) / kS.length });
                }
                for (let i = 0; i < res.length; i++) {
                    let s = 0, c = 0;
                    for (let j = Math.max(0, i - dP + 1); j <= i; j++) { s += res[j]._k; c++; }
                    res[i] = { k: res[i]._k, d: s / c };
                }
                return res;
            },
        });

        // Alligator (jaw 13 / teeth 8 / lips 5 smoothed MAs of median price, shifted)
        const smma = (src, p) => { const out = []; let prev; for (let i = 0; i < src.length; i++) { if (i < p - 1) { out.push(undefined); continue; } if (i === p - 1) { prev = src.slice(0, p).reduce((a, b) => a + b, 0) / p; } else { prev = (prev * (p - 1) + src[i]) / p; } out.push(prev); } return out; };
        const shift = (arr, s) => { const out = new Array(arr.length); for (let i = 0; i < arr.length; i++) { const t = i + s; if (t < arr.length) out[t] = arr[i]; } return out; };
        reg({
            name: 'ALLIGATOR', shortName: 'ALLIGATOR', calcParams: [13, 8, 5],
            figures: [
                { key: 'jaw', title: 'Jaw: ', type: 'line' },
                { key: 'teeth', title: 'Teeth: ', type: 'line' },
                { key: 'lips', title: 'Lips: ', type: 'line' },
            ],
            calc: (data) => {
                const med = data.map(d => (d.high + d.low) / 2);
                const jaw = shift(smma(med, 13), 8), teeth = shift(smma(med, 8), 5), lips = shift(smma(med, 5), 3);
                return data.map((_, i) => ({ jaw: jaw[i], teeth: teeth[i], lips: lips[i] }));
            },
        });

        // Ichimoku Kinko Hyo
        reg({
            name: 'ICHIMOKU', shortName: 'ICHIMOKU', calcParams: [9, 26, 52],
            figures: [
                { key: 'tenkan', title: 'Tenkan: ', type: 'line' },
                { key: 'kijun', title: 'Kijun: ', type: 'line' },
                { key: 'spanA', title: 'Span A: ', type: 'line' },
                { key: 'spanB', title: 'Span B: ', type: 'line' },
                { key: 'chikou', title: 'Chikou: ', type: 'line' },
            ],
            calc: (data, { calcParams: [a, b, c] }) => {
                const hh = (i, n) => { let m = -Infinity; for (let j = Math.max(0, i - n + 1); j <= i; j++) m = Math.max(m, data[j].high); return m; };
                const ll = (i, n) => { let m = Infinity; for (let j = Math.max(0, i - n + 1); j <= i; j++) m = Math.min(m, data[j].low); return m; };
                const out = data.map((_, i) => ({ tenkan: (hh(i, a) + ll(i, a)) / 2, kijun: (hh(i, b) + ll(i, b)) / 2 }));
                for (let i = 0; i < data.length; i++) {
                    const t = i + b;
                    if (t < out.length) { out[t].spanA = (out[i].tenkan + out[i].kijun) / 2; out[t].spanB = (hh(i, c) + ll(i, c)) / 2; }
                    const k = i - b;
                    if (k >= 0) out[k].chikou = data[i].close;
                }
                return out;
            },
        });

        // Market Facilitation Index (Bill Williams)
        reg({
            name: 'BWMFI', shortName: 'BW MFI',
            figures: [{ key: 'mfi', title: 'MFI: ', type: 'line' }],
            calc: (data) => data.map(d => ({ mfi: d.volume ? (d.high - d.low) / d.volume : 0 })),
        });
    }

    function toggleIndicator(ind) {
        if (!chart) return;
        if (activeIndicators[ind.name] != null) {
            chart.removeIndicator(activeIndicators[ind.name], ind.name);
            delete activeIndicators[ind.name];
        } else {
            const paneId = ind.overlay
                ? chart.createIndicator(ind.name, true, { id: 'candle_pane' })
                : chart.createIndicator(ind.name, false);
            activeIndicators[ind.name] = paneId ?? 'candle_pane';
        }
        renderIndicatorMenu();
    }

    function removeAllIndicators() {
        Object.entries(activeIndicators).forEach(([name, paneId]) => chart.removeIndicator(paneId, name));
        for (const k in activeIndicators) delete activeIndicators[k];
        renderIndicatorMenu();
    }

    function renderIndicatorMenu() {
        const menu = document.getElementById('ind-menu');
        const cats = ['Trend', 'Oscillators', 'Volumes', 'Bill Williams'];
        const activeCount = Object.keys(activeIndicators).length;
        let html = `<div class="flex items-center justify-between px-3 py-2 border-b border-edge sticky top-0 bg-panel2">
            <span class="text-gray-400">${INDICATORS.length} indicators</span>
            <button data-ind-clear class="text-down hover:underline ${activeCount ? '' : 'opacity-40 pointer-events-none'}">Remove all (${activeCount})</button>
        </div>`;
        for (const cat of cats) {
            html += `<div class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-wide text-gray-500">${cat}</div>`;
            for (const ind of INDICATORS.filter(i => i.cat === cat)) {
                const on = activeIndicators[ind.name] != null;
                html += `<button data-ind="${ind.name}" class="flex items-center justify-between w-full text-left px-3 py-1.5 hover:bg-panel ${on ? 'text-accent' : 'text-gray-300'}">
                    <span>${ind.label}</span><span>${on ? '✓' : ''}</span>
                </button>`;
            }
        }
        menu.innerHTML = html;
    }

    document.getElementById('ind-btn').addEventListener('click', e => {
        e.stopPropagation();
        const menu = document.getElementById('ind-menu');
        const show = menu.classList.contains('hidden');
        if (show) renderIndicatorMenu();
        menu.classList.toggle('hidden', !show);
    });
    document.getElementById('ind-menu').addEventListener('click', e => {
        e.stopPropagation();
        if (e.target.closest('[data-ind-clear]')) { removeAllIndicators(); return; }
        const btn = e.target.closest('[data-ind]');
        if (btn) toggleIndicator(INDICATORS.find(i => i.name === btn.dataset.ind));
    });
    document.addEventListener('click', () => document.getElementById('ind-menu').classList.add('hidden'));

    // ---- Drawing tools (KLineCharts overlays) ----
    const DRAW_TOOLS = [
        { name: 'cursor', glyph: '⤡', title: 'Cursor (no tool)' },
        { name: 'horizontalStraightLine', glyph: '─', title: 'Horizontal line' },
        { name: 'verticalStraightLine', glyph: '│', title: 'Vertical line' },
        { name: 'segment', glyph: '╱', title: 'Trend line' },
        { name: 'rayLine', glyph: '→', title: 'Ray' },
        { name: 'straightLine', glyph: '↔', title: 'Extended line' },
        { name: 'priceLine', glyph: 'P', title: 'Price line' },
        { name: 'parallelStraightLine', glyph: '∥', title: 'Equidistant channel' },
        { name: 'priceChannelLine', glyph: '≣', title: 'Price channel' },
        { name: 'fibonacciLine', glyph: 'F', title: 'Fibonacci retracement' },
        { name: 'rect', glyph: '▭', title: 'Rectangle' },
        { name: 'circle', glyph: '◯', title: 'Circle' },
        { name: 'triangle', glyph: '△', title: 'Triangle' },
        { name: 'arrow', glyph: '➜', title: 'Arrow' },
        { name: 'text', glyph: 'T', title: 'Text label' },
        { name: 'remove', glyph: '🗑', title: 'Remove all drawings' },
    ];
    let activeTool = 'cursor';

    function registerCustomOverlays() {
        if (!window.klinecharts || window.__overlaysRegistered) return;
        window.__overlaysRegistered = true;
        const reg = klinecharts.registerOverlay;
        const BLUE = '#3b82f6';

        reg({
            name: 'rect', totalStep: 3,
            createPointFigures: ({ coordinates }) => {
                if (coordinates.length < 2) return [];
                const [a, b] = coordinates;
                return [{ type: 'polygon', attrs: { coordinates: [{ x: a.x, y: a.y }, { x: b.x, y: a.y }, { x: b.x, y: b.y }, { x: a.x, y: b.y }] }, styles: { style: 'stroke', borderColor: BLUE } }];
            },
        });
        reg({
            name: 'circle', totalStep: 3,
            createPointFigures: ({ coordinates }) => {
                if (coordinates.length < 2) return [];
                const [c, e] = coordinates;
                const r = Math.hypot(e.x - c.x, e.y - c.y);
                return [{ type: 'circle', attrs: { x: c.x, y: c.y, r }, styles: { style: 'stroke', borderColor: BLUE } }];
            },
        });
        reg({
            name: 'triangle', totalStep: 4,
            createPointFigures: ({ coordinates }) => {
                if (coordinates.length < 3) return [];
                return [{ type: 'polygon', attrs: { coordinates: coordinates.slice(0, 3) }, styles: { style: 'stroke', borderColor: BLUE } }];
            },
        });
        reg({
            name: 'arrow', totalStep: 3,
            createPointFigures: ({ coordinates }) => {
                if (coordinates.length < 2) return [];
                const [a, b] = coordinates;
                const ang = Math.atan2(b.y - a.y, b.x - a.x);
                const h = 9;
                const head = [
                    { x: b.x, y: b.y },
                    { x: b.x - h * Math.cos(ang - Math.PI / 7), y: b.y - h * Math.sin(ang - Math.PI / 7) },
                    { x: b.x - h * Math.cos(ang + Math.PI / 7), y: b.y - h * Math.sin(ang + Math.PI / 7) },
                ];
                return [
                    { type: 'line', attrs: { coordinates: [a, b] }, styles: { color: BLUE } },
                    { type: 'polygon', attrs: { coordinates: head }, styles: { style: 'fill', color: BLUE } },
                ];
            },
        });
        reg({
            name: 'text', totalStep: 2,
            createPointFigures: ({ coordinates, overlay }) => {
                if (coordinates.length < 1) return [];
                return [{ type: 'text', attrs: { x: coordinates[0].x, y: coordinates[0].y, text: overlay.extendData || 'Text' }, styles: { color: '#e5e7eb', size: 12 } }];
            },
        });
    }

    function selectTool(name) {
        if (name === 'remove') { chart && chart.removeOverlay(); setActiveTool('cursor'); return; }
        setActiveTool(name);
        if (name === 'cursor' || !chart) return;

        if (name === 'text') {
            chart.createOverlay({
                name: 'text',
                onDrawEnd: (o) => {
                    const t = prompt('Label text:', 'Note');
                    chart.overrideOverlay({ id: o.overlay.id, extendData: t || 'Note' });
                    setActiveTool('cursor');
                    return true;
                },
            });
        } else {
            chart.createOverlay({ name, onDrawEnd: () => { setActiveTool('cursor'); return true; } });
        }
    }

    function setActiveTool(name) {
        activeTool = name;
        document.querySelectorAll('#draw-toolbar [data-tool]').forEach(b => {
            const active = b.dataset.tool === name && name !== 'cursor';
            b.classList.toggle('text-accent', active);
            b.classList.toggle('bg-panel2', active);
        });
    }

    function renderDrawToolbar() {
        document.getElementById('draw-toolbar').innerHTML = DRAW_TOOLS.map(t =>
            `<button data-tool="${t.name}" title="${t.title}" class="w-7 h-7 rounded flex items-center justify-center text-gray-400 hover:text-white hover:bg-panel2 ${t.name === 'remove' ? 'mt-1 text-down' : ''}">${t.glyph}</button>`
        ).join('');
    }
    document.getElementById('draw-toolbar').addEventListener('click', e => {
        const b = e.target.closest('[data-tool]');
        if (b) selectTool(b.dataset.tool);
    });

    // ---- Toolbox tabs (Trade / History / Journal) ----
    function setTbTab(tab) {
        ['trade', 'history', 'journal'].forEach(t => {
            const el = document.getElementById('tb-' + t);
            const on = t === tab;
            el.classList.toggle('hidden', !on);
            if (t === 'history') el.classList.toggle('flex', on);
        });
        document.querySelectorAll('.tb-tab').forEach(b => {
            const on = b.dataset.tbtab === tab;
            b.classList.toggle('text-accent', on);
            b.classList.toggle('border-accent', on);
            b.classList.toggle('text-gray-400', !on);
            b.classList.toggle('border-transparent', !on);
        });
        if (tab === 'history') loadHistory();
        if (tab === 'journal') loadJournal();
    }
    document.querySelectorAll('.tb-tab').forEach(b => b.addEventListener('click', () => setTbTab(b.dataset.tbtab)));

    async function loadHistory() {
        const { ok, json } = await api('/api/history');
        if (!ok) return;
        const s = json.data.summary;
        const pnlC = s.closed_pnl > 0 ? 'text-up' : s.closed_pnl < 0 ? 'text-down' : 'text-gray-300';
        document.getElementById('history-summary').innerHTML =
            `<span>Closed P/L: <b class="${pnlC}">${fmt(s.closed_pnl)} ${s.currency}</b></span>` +
            `<span>Trades: <b class="text-gray-200">${s.trades}</b></span>` +
            `<span>Win rate: <b class="text-gray-200">${s.win_rate == null ? '—' : s.win_rate + '%'}</b> <span class="text-gray-600">(${s.wins}W / ${s.losses}L)</span></span>` +
            `<span>Deposits: <b class="text-gray-200">${fmt(s.deposits)}</b></span>` +
            (s.withdrawals ? `<span>Withdrawals: <b class="text-gray-200">${fmt(s.withdrawals)}</b></span>` : '');
        const tbody = document.getElementById('history-rows');
        const rows = json.data.positions;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-4 text-center text-gray-500">No closed trades yet</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(p => {
            const d = digitsBySymbol[p.symbol] ?? 5;
            const pc = p.profit > 0 ? 'text-up' : p.profit < 0 ? 'text-down' : 'text-gray-300';
            const sc = p.side === 'buy' ? 'text-up' : 'text-down';
            const closed = p.closed_at ? new Date(p.closed_at).toLocaleString() : '—';
            return `<tr class="border-t border-edge/50">
                <td class="px-3 py-1.5 tabular-nums text-gray-400">${p.ticket}</td>
                <td class="px-2 py-1.5 font-medium">${p.symbol}</td>
                <td class="px-2 py-1.5 uppercase ${sc}">${p.side}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.volume)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.open_price, d)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.close_price, d)}</td>
                <td class="px-2 py-1.5 text-gray-400 whitespace-nowrap">${closed}</td>
                <td class="px-3 py-1.5 text-right tabular-nums ${pc}">${fmt(p.profit)}</td>
            </tr>`;
        }).join('');
    }

    // ---- Journal (persistent server log + live client events) ----
    const journalEl = document.getElementById('tb-journal');
    let serverJournal = [];
    let localJournal = []; // client-only diagnostics: startup, connectivity
    const esc = s => String(s).replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

    // Client-side diagnostic entry (not persisted).
    function journal(message, level = 'info') {
        localJournal.push({ at: new Date().toISOString(), level, message });
        if (localJournal.length > 200) localJournal.shift();
        renderJournal();
    }

    async function loadJournal() {
        const { ok, json } = await api('{{ route('api.journal') }}');
        if (ok) { serverJournal = json.data; renderJournal(); }
    }

    function renderJournal() {
        const all = [...serverJournal, ...localJournal]
            .sort((a, b) => new Date(a.at) - new Date(b.at));
        const colorOf = l => l === 'error' ? 'text-down' : l === 'success' ? 'text-up' : l === 'warn' ? 'text-yellow-400' : 'text-gray-400';
        journalEl.innerHTML = all.map(e => {
            const t = e.at ? new Date(e.at).toLocaleTimeString() : '';
            return `<div><span class="text-gray-600">${t}</span> <span class="${colorOf(e.level)}">${esc(e.message)}</span></div>`;
        }).join('');
        scrollJournal();
    }
    function scrollJournal() { journalEl.scrollTop = journalEl.scrollHeight; }

    // ---- Deposit / Withdraw ----
    document.getElementById('funds-btn').addEventListener('click', () => {
        document.getElementById('funds-balance').textContent = document.querySelector('[data-acc="balance"]').textContent;
        document.getElementById('funds-msg').textContent = '';
        document.getElementById('funds-amount').value = '';
        document.getElementById('funds-modal').classList.remove('hidden');
    });
    document.getElementById('funds-close').addEventListener('click', () =>
        document.getElementById('funds-modal').classList.add('hidden'));
    document.getElementById('funds-modal').addEventListener('click', e => {
        if (e.target.id === 'funds-modal') e.target.classList.add('hidden');
    });
    async function fund(action) {
        const amount = parseFloat(document.getElementById('funds-amount').value);
        const msg = document.getElementById('funds-msg');
        if (!amount || amount <= 0) { msg.className = 'text-xs min-h-[1rem] text-down'; msg.textContent = 'Enter a valid amount.'; return; }
        const { ok, json } = await api(`/api/account/${action}`, { method: 'POST', body: JSON.stringify({ amount }) });
        if (ok) {
            msg.className = 'text-xs min-h-[1rem] text-up';
            msg.textContent = `${action === 'deposit' ? 'Deposited' : 'Withdrew'} ${fmt(Math.abs(json.data.amount))} — balance ${fmt(json.data.balance_after)}`;
            document.getElementById('funds-balance').textContent = fmt(json.data.balance_after);
            loadAccount(); loadJournal();
        } else {
            msg.className = 'text-xs min-h-[1rem] text-down';
            msg.textContent = json.error?.message || (json.errors ? Object.values(json.errors)[0][0] : 'Failed.');
        }
    }
    document.getElementById('funds-deposit').addEventListener('click', () => fund('deposit'));
    document.getElementById('funds-withdraw').addEventListener('click', () => fund('withdraw'));

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
    initChart();
    renderDrawToolbar();
    journal('Terminal started — {{ config('app.name') }}');
    journal('Connected to account #{{ $account->login }} ({{ $account->currency }}, leverage 1:{{ $account->leverage }})', 'success');
    if (selected) selectSymbol(selected);
    loadQuotes(); loadAccount(); loadPositions(); loadOrders(); loadJournal();
    setInterval(() => {
        loadQuotes(); loadPositions(); loadOrders();
        if (!document.getElementById('tb-journal').classList.contains('hidden')) loadJournal();
    }, 1500);
    setInterval(loadAccount, 2000);
})();
</script>
@endif
@endsection
