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
            <div class="px-3 py-2 text-xs uppercase tracking-wide text-gray-400 border-b border-edge">Market Watch</div>
            <div class="overflow-auto">
                <table class="w-full text-xs">
                    <thead class="text-gray-500 sticky top-0 bg-panel">
                        <tr>
                            <th class="text-left px-3 py-1.5 font-medium">Symbol</th>
                            <th class="text-right px-2 py-1.5 font-medium">Bid</th>
                            <th class="text-right px-2 py-1.5 font-medium">Ask</th>
                            <th class="text-right px-3 py-1.5 font-medium">Spr</th>
                        </tr>
                    </thead>
                    <tbody id="watchlist">
                        @foreach ($instruments as $ins)
                        <tr class="border-t border-edge/50 hover:bg-panel2 cursor-pointer select-none"
                            data-symbol="{{ $ins->symbol }}" data-digits="{{ $ins->digits }}">
                            <td class="px-3 py-1.5 font-medium text-gray-200">{{ $ins->symbol }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums" data-field="bid">—</td>
                            <td class="px-2 py-1.5 text-right tabular-nums" data-field="ask">—</td>
                            <td class="px-3 py-1.5 text-right tabular-nums text-gray-500" data-field="spread">—</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Order panel --}}
        <section class="col-span-12 md:col-span-8 lg:col-span-3 bg-panel p-4 flex flex-col gap-3">
            <div class="text-xs uppercase tracking-wide text-gray-400">New Order</div>

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

            <div class="grid grid-cols-2 gap-2 mt-1">
                <button id="btn-sell" class="bg-down hover:brightness-110 text-white font-semibold rounded-md py-2.5 text-sm transition">SELL</button>
                <button id="btn-buy" class="bg-up hover:brightness-110 text-white font-semibold rounded-md py-2.5 text-sm transition">BUY</button>
            </div>

            <div id="order-msg" class="text-xs min-h-[1rem]"></div>
        </section>

        {{-- Open positions --}}
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
                            <th class="text-right px-2 py-1.5 font-medium">Profit</th>
                            <th class="px-3 py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody id="positions">
                        <tr><td colspan="8" class="px-3 py-4 text-center text-gray-500">No open positions</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
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
                updateCell(row.querySelector('[data-field="bid"]'), q.bid, lastQuotes[q.symbol]?.bid, d);
                updateCell(row.querySelector('[data-field="ask"]'), q.ask, lastQuotes[q.symbol]?.ask, d);
                row.querySelector('[data-field="spread"]').textContent = q.spread ?? '—';
            }
            lastQuotes[q.symbol] = q;
        }
        renderOrderPanel();
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
            tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-4 text-center text-gray-500">No open positions</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(p => {
            const d = digitsBySymbol[p.symbol] ?? 5;
            const pc = p.profit > 0 ? 'text-up' : p.profit < 0 ? 'text-down' : 'text-gray-300';
            const sc = p.side === 'buy' ? 'text-up' : 'text-down';
            return `<tr class="border-t border-edge/50">
                <td class="px-3 py-1.5 tabular-nums text-gray-400">${p.ticket}</td>
                <td class="px-2 py-1.5 font-medium">${p.symbol}</td>
                <td class="px-2 py-1.5 uppercase ${sc}">${p.side}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.volume)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.open_price, d)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">${fmt(p.current_price, d)}</td>
                <td class="px-2 py-1.5 text-right tabular-nums ${pc}">${fmt(p.profit)}</td>
                <td class="px-3 py-1.5 text-right">
                    <button data-close="${p.id}" class="text-gray-400 hover:text-down" title="Close">✕</button>
                </td>
            </tr>`;
        }).join('');
    }

    async function placeOrder(side) {
        const msg = document.getElementById('order-msg');
        msg.className = 'text-xs min-h-[1rem] text-gray-400';
        msg.textContent = 'Placing order…';
        const body = {
            symbol: selected,
            side,
            volume: parseFloat(document.getElementById('order-volume').value),
            stop_loss: parseFloat(document.getElementById('order-sl').value) || null,
            take_profit: parseFloat(document.getElementById('order-tp').value) || null,
        };
        const { ok, json } = await api('{{ route('api.orders.store') }}', { method: 'POST', body: JSON.stringify(body) });
        if (ok) {
            msg.className = 'text-xs min-h-[1rem] text-up';
            msg.textContent = `${side.toUpperCase()} ${body.volume} ${selected} filled @ ${json.data.open_price} (#${json.data.ticket})`;
            await Promise.all([loadPositions(), loadAccount()]);
        } else {
            msg.className = 'text-xs min-h-[1rem] text-down';
            msg.textContent = json.error?.message || 'Order failed.';
        }
    }

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

    // Event wiring
    document.getElementById('watchlist').addEventListener('click', e => {
        const row = e.target.closest('tr[data-symbol]');
        if (row) selectSymbol(row.dataset.symbol);
    });
    document.getElementById('positions').addEventListener('click', e => {
        const btn = e.target.closest('[data-close]');
        if (btn) closePosition(btn.dataset.close);
    });
    document.getElementById('btn-buy').addEventListener('click', () => placeOrder('buy'));
    document.getElementById('btn-sell').addEventListener('click', () => placeOrder('sell'));

    // Initial load + polling
    if (selected) selectSymbol(selected);
    loadQuotes(); loadAccount(); loadPositions();
    setInterval(() => { loadQuotes(); loadPositions(); }, 1500);
    setInterval(loadAccount, 2000);
})();
</script>
@endif
@endsection
