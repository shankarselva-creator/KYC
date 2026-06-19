# Forex Web Trader

A web-based forex trading terminal inspired by **MetaTrader 4 WebTrader**, built
with **Laravel 13** and **MySQL**.

This first iteration is a **core trading MVP**:

- 🔐 Registration / login (each new user gets a funded **demo account**)
- 📈 Live **Market Watch** for 10 instruments (majors + Gold)
- 🧾 **Order ticket** — place market **Buy / Sell** orders with optional SL / TP
- 💼 **Open positions** with live floating P/L and one-click close
- 📊 Account metrics: **balance, equity, used / free margin, margin level**
- 🕘 Closed-trade **history**
- 🔌 Pluggable **price feed** (external HTTP provider or a built-in simulator)

The UI is a single-page-feel Blade terminal (dark theme) that polls a small,
session-authenticated JSON API.

---

## Requirements

- PHP 8.3+
- Composer
- MySQL 8 (or SQLite for quick local trials)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Point .env at your MySQL database (DB_DATABASE / DB_USERNAME / DB_PASSWORD),
# then create the schema and seed demo data:
php artisan migrate --seed
```

Seeding creates the instruments, an initial set of quotes, and a demo trader:

```
email:    trader@example.com
password: password
```

## Running

```bash
# Terminal 1 — web app
php artisan serve            # http://127.0.0.1:8000

# Terminal 2 — keep prices ticking (near real-time)
php artisan quotes:poll --loop
```

Open <http://127.0.0.1:8000>, log in, and trade.

> In production schedule the feed instead: Laravel's scheduler runs
> `quotes:poll` every minute (`routes/console.php`), or run the `--loop` worker
> under a process supervisor.

---

## Price feed

Configured in `config/markets.php` and `.env`:

```env
MARKET_DATA_DRIVER=external        # or "simulated"
MARKET_DATA_API_URL=https://api.your-fx-vendor.com/v1/quotes
MARKET_DATA_API_KEY=...
```

- **`simulated`** — a seeded random walk; no network or keys needed (great for dev).
- **`external`** — polls a JSON HTTP vendor. Map the vendor's payload fields and
  auth scheme in the `external` block of `config/markets.php`; no code changes
  needed for most providers.

---

## Tests

```bash
php artisan test
```

Covers the trading engine (P/L, margin, order rejection) and the JSON API
(registration provisioning, quotes, open/close, validation, authorization).

---

## Project layout

See [`CLAUDE.md`](CLAUDE.md) for architecture, data models, API reference, and
conventions.
