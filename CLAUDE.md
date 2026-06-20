# CLAUDE.md — Forex Web Trader

This file provides guidance for AI assistants working in this repository.

---

## Project Overview

**Forex Web Trader** — a web-based forex trading terminal inspired by MetaTrader 4
WebTrader. It provides a live market watch, an order ticket, open-position
management with floating P/L, account metrics (balance / equity / margin), and a
closed-trade history.

> The repository is named "KYC" for historical reasons but this is a forex
> trading application.

---

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.3+ |
| Framework | Laravel 13 |
| Database | MySQL 8 (SQLite used for tests / quick local dev) |
| Frontend | Blade + vanilla JS, Tailwind via Play CDN (no build step required) |
| Auth | Session-based (Laravel auth), CSRF-protected JSON API |
| Testing | PHPUnit (`php artisan test`) |

---

## Architecture

```
app/
├── Console/Commands/
│   └── PollQuotes.php           # `quotes:poll` — refresh prices (supports --loop)
├── Http/Controllers/
│   ├── Auth/                    # register + login/logout
│   ├── Api/                     # JSON endpoints (quotes, account, orders, positions)
│   ├── Concerns/                # ApiResponses + ResolvesTradingAccount traits
│   └── DashboardController.php   # terminal + history pages
├── Models/                      # Instrument, Quote, TradingAccount, Position, Transaction, User
└── Services/
    ├── MarketData/              # price feed: provider interface + drivers + QuoteService
    └── Trading/                 # TradingService, CurrencyConverter, AccountProvisioner
```

### Market data (price feed)

- Driver-based, selected by `MARKET_DATA_DRIVER` (`external` | `simulated`).
- `MarketDataProvider` is the interface; `ExternalHttpProvider` polls a configurable
  HTTP JSON vendor, `SimulatedProvider` generates a random walk for offline dev.
- `QuoteService::refresh()` fetches ticks, upserts the `quotes` table, appends
  `ticks`, and feeds `CandleService::ingest()` to build live OHLC candles.
- `CandleService` buckets ticks into 9 timeframes (M1..MN), seeds synthetic
  history, and serves candles for the chart.
- Drivers are bound in `AppServiceProvider`; configured in `config/markets.php`.

### Charting (frontend)

- The terminal embeds **KLineCharts** (vendored at `public/vendor/klinecharts.min.js`,
  no CDN). 3 chart types (Candles / Bars / Line) and 9 timeframes; the latest
  candle updates live from polled quotes.

### Trading engine

- `TradingService` opens/closes market positions and computes realised & floating
  P/L, required margin, and live account metrics (equity, free margin, margin level).
- P/L is computed in the instrument's quote currency, then converted to the account
  currency via `CurrencyConverter` (uses current mid-prices; falls back to parity).
- `AccountProvisioner` creates a funded demo account on registration.
- `TradingEngine` is the matching loop: on each tick it fills pending orders whose
  trigger is reached and closes positions that hit SL/TP. Run from `quotes:poll`
  (after `QuoteService::refresh()`), so the loop/scheduler drives execution.

---

## Data Models

```
Instrument       symbol, base/quote currency, digits, pip_size, contract_size, volumes,
                 swap_long/short, stops_level, category
Quote            instrument_id (unique), bid, ask, day_open (+date), quoted_at  (latest tick)
Tick             instrument_id, bid, ask, tick_at   (rolling history for the tick chart)
Candle           instrument_id, timeframe(M1..MN), opened_at, OHLC, volume  (chart data)
TradingAccount   user_id, login, type(demo|live), currency, leverage, balance
Position         ticket, account, instrument, side(buy|sell), volume, open/close price,
                 sl, tp, commission, swap, profit, status(open|closed), timestamps
Order            ticket, account, instrument, type(buy/sell _limit/_stop), volume, price,
                 sl, tp, status(pending|filled|cancelled|expired), position_id  (pending orders)
Transaction      account, position, type, amount, balance_after   (ledger)
```

---

## API (session-authenticated, under `/api`, consumed by the terminal)

| Method | Route | Purpose |
|---|---|---|
| GET  | `/api/quotes` | Active instruments + latest bid/ask/spread + daily change |
| GET  | `/api/instruments/{symbol}/specification` | Contract spec: digits, swaps, margin/lot |
| GET  | `/api/instruments/{symbol}/ticks` | Recent tick history (tick chart) |
| GET  | `/api/instruments/{symbol}/candles` | OHLC candles (`timeframe=M1..MN`) for the chart |
| GET  | `/api/account` | Account info + live metrics |
| GET  | `/api/positions` | Open positions with live P/L |
| GET  | `/api/orders` | Pending orders (limit/stop) |
| POST | `/api/orders` | Market order (`type=buy\|sell`) or pending (`type=*_limit\|*_stop, price`) |
| POST | `/api/orders/{order}/cancel` | Cancel a pending order |
| POST | `/api/positions/{position}/close` | Close a position |
| POST | `/api/positions/{position}/modify` | Update a position's SL/TP |

Response envelope: `{ "data": ..., "meta": {}, "error": null }`.
Errors: `{ "data": null, "error": { "code", "message", "details" } }`.

---

## Development Workflow

```bash
composer install
cp .env.example .env && php artisan key:generate
# Configure MySQL in .env (DB_*), or set DB_CONNECTION=sqlite for quick local dev.
php artisan migrate --seed          # seeds instruments, quotes, a demo trader
php artisan quotes:poll --loop      # keep prices ticking (separate terminal)
php artisan serve                   # http://127.0.0.1:8000

php artisan test                    # run the suite
```

Seeded demo login: `trader@example.com` / `password`.

---

## Conventions

- **Read a file before editing it.** Match surrounding style.
- Keep business logic in `app/Services`; controllers stay thin.
- All migrations must be reversible (`down()` present).
- Validate all API input (`$request->validate`); never trust client-supplied
  prices, account ids, or status fields — recompute server-side.
- API responses must use the envelope above (`ApiResponses` trait).
- Add/extend tests for any new service method or endpoint; run `php artisan test`
  before committing.
- Commit messages follow Conventional Commits (e.g. `feat(trading): add pending orders`).
- New env vars must be added to `.env.example`.

---

## Roadmap (not yet built)

Chart indicators (MA/MACD/RSI/…) and drawing tools, WebSocket streaming prices,
toolbox Journal tab, multiple accounts per user, deposits/withdrawals UI,
swap/commission accrual.

---

*Last updated: 2026-06-19*
