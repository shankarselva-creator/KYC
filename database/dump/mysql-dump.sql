-- Forex Web Trader — MySQL dump
-- Generated 2026-06-21 15:10:20

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

create table `users` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `email` varchar(255) not null, `email_verified_at` timestamp null, `password` varchar(255) not null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `users` add unique `users_email_unique`(`email`);
create table `password_reset_tokens` (`email` varchar(255) not null, `token` varchar(255) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);
create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cache` add index `cache_expiration_index`(`expiration`);
create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cache_locks` add index `cache_locks_expiration_index`(`expiration`);
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` smallint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `jobs` add index `jobs_queue_index`(`queue`);
create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` varchar(255) not null, `queue` varchar(255) not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `failed_jobs` add index `failed_jobs_connection_queue_failed_at_index`(`connection`, `queue`, `failed_at`);
alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);
create table `instruments` (`id` bigint unsigned not null auto_increment primary key, `symbol` varchar(20) not null, `base_currency` varchar(10) not null, `quote_currency` varchar(10) not null, `description` varchar(255) null, `digits` tinyint unsigned not null default '5', `pip_size` decimal(12, 8) not null default '0.0001', `contract_size` int unsigned not null default '100000', `min_volume` decimal(8, 2) not null default '0.01', `max_volume` decimal(8, 2) not null default '100', `volume_step` decimal(8, 2) not null default '0.01', `is_active` tinyint(1) not null default '1', `sort_order` smallint unsigned not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `instruments` add index `instruments_is_active_sort_order_index`(`is_active`, `sort_order`);
alter table `instruments` add unique `instruments_symbol_unique`(`symbol`);
create table `trading_accounts` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `login` varchar(20) not null, `name` varchar(255) null, `type` enum('demo', 'live') not null default 'demo', `currency` varchar(10) not null default 'USD', `leverage` int unsigned not null default '100', `balance` decimal(18, 2) not null default '0', `is_active` tinyint(1) not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `trading_accounts` add constraint `trading_accounts_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `trading_accounts` add index `trading_accounts_user_id_index`(`user_id`);
alter table `trading_accounts` add unique `trading_accounts_login_unique`(`login`);
create table `quotes` (`id` bigint unsigned not null auto_increment primary key, `instrument_id` bigint unsigned not null, `bid` decimal(18, 8) not null, `ask` decimal(18, 8) not null, `quoted_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `quotes` add constraint `quotes_instrument_id_foreign` foreign key (`instrument_id`) references `instruments` (`id`) on delete cascade;
alter table `quotes` add unique `quotes_instrument_id_unique`(`instrument_id`);
create table `positions` (`id` bigint unsigned not null auto_increment primary key, `ticket` bigint unsigned not null, `trading_account_id` bigint unsigned not null, `instrument_id` bigint unsigned not null, `side` enum('buy', 'sell') not null, `volume` decimal(8, 2) not null, `open_price` decimal(18, 8) not null, `close_price` decimal(18, 8) null, `stop_loss` decimal(18, 8) null, `take_profit` decimal(18, 8) null, `commission` decimal(18, 2) not null default '0', `swap` decimal(18, 2) not null default '0', `profit` decimal(18, 2) not null default '0', `status` enum('open', 'closed') not null default 'open', `opened_at` timestamp not null, `closed_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `positions` add constraint `positions_trading_account_id_foreign` foreign key (`trading_account_id`) references `trading_accounts` (`id`) on delete cascade;
alter table `positions` add constraint `positions_instrument_id_foreign` foreign key (`instrument_id`) references `instruments` (`id`) on delete restrict;
alter table `positions` add index `positions_trading_account_id_status_index`(`trading_account_id`, `status`);
alter table `positions` add unique `positions_ticket_unique`(`ticket`);
create table `transactions` (`id` bigint unsigned not null auto_increment primary key, `trading_account_id` bigint unsigned not null, `position_id` bigint unsigned null, `type` enum('deposit', 'withdrawal', 'trade', 'commission', 'swap', 'adjustment') not null, `amount` decimal(18, 2) not null, `balance_after` decimal(18, 2) not null, `description` varchar(255) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `transactions` add constraint `transactions_trading_account_id_foreign` foreign key (`trading_account_id`) references `trading_accounts` (`id`) on delete cascade;
alter table `transactions` add constraint `transactions_position_id_foreign` foreign key (`position_id`) references `positions` (`id`) on delete set null;
alter table `transactions` add index `transactions_trading_account_id_created_at_index`(`trading_account_id`, `created_at`);
alter table `instruments` add `swap_long` decimal(10, 2) not null default '0' after `volume_step`;
alter table `instruments` add `swap_short` decimal(10, 2) not null default '0' after `swap_long`;
alter table `instruments` add `stops_level` int unsigned not null default '0' after `swap_short`;
alter table `instruments` add `category` varchar(40) not null default 'Forex' after `stops_level`;
alter table `quotes` add `day_open` decimal(18, 8) null after `ask`;
alter table `quotes` add `day_open_date` date null after `day_open`;
create table `ticks` (`id` bigint unsigned not null auto_increment primary key, `instrument_id` bigint unsigned not null, `bid` decimal(18, 8) not null, `ask` decimal(18, 8) not null, `tick_at` timestamp not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `ticks` add constraint `ticks_instrument_id_foreign` foreign key (`instrument_id`) references `instruments` (`id`) on delete cascade;
alter table `ticks` add index `ticks_instrument_id_tick_at_index`(`instrument_id`, `tick_at`);
create table `orders` (`id` bigint unsigned not null auto_increment primary key, `ticket` bigint unsigned not null, `trading_account_id` bigint unsigned not null, `instrument_id` bigint unsigned not null, `type` enum('buy_limit', 'sell_limit', 'buy_stop', 'sell_stop') not null, `volume` decimal(8, 2) not null, `price` decimal(18, 8) not null, `stop_loss` decimal(18, 8) null, `take_profit` decimal(18, 8) null, `status` enum('pending', 'filled', 'cancelled', 'expired') not null default 'pending', `position_id` bigint unsigned null, `expires_at` timestamp null, `placed_at` timestamp not null, `filled_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `orders` add constraint `orders_trading_account_id_foreign` foreign key (`trading_account_id`) references `trading_accounts` (`id`) on delete cascade;
alter table `orders` add constraint `orders_instrument_id_foreign` foreign key (`instrument_id`) references `instruments` (`id`) on delete restrict;
alter table `orders` add constraint `orders_position_id_foreign` foreign key (`position_id`) references `positions` (`id`) on delete set null;
alter table `orders` add index `orders_trading_account_id_status_index`(`trading_account_id`, `status`);
alter table `orders` add index `orders_status_instrument_id_index`(`status`, `instrument_id`);
alter table `orders` add unique `orders_ticket_unique`(`ticket`);
create table `candles` (`id` bigint unsigned not null auto_increment primary key, `instrument_id` bigint unsigned not null, `timeframe` varchar(4) not null, `opened_at` timestamp not null, `open` decimal(18, 8) not null, `high` decimal(18, 8) not null, `low` decimal(18, 8) not null, `close` decimal(18, 8) not null, `volume` int unsigned not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `candles` add constraint `candles_instrument_id_foreign` foreign key (`instrument_id`) references `instruments` (`id`) on delete cascade;
alter table `candles` add unique `candles_instrument_id_timeframe_opened_at_unique`(`instrument_id`, `timeframe`, `opened_at`);
alter table `candles` add index `candles_instrument_id_timeframe_opened_at_index`(`instrument_id`, `timeframe`, `opened_at`);
create table `journal_entries` (`id` bigint unsigned not null auto_increment primary key, `trading_account_id` bigint unsigned not null, `level` enum('info', 'success', 'warn', 'error') not null default 'info', `category` varchar(30) not null default 'trade', `message` varchar(255) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `journal_entries` add constraint `journal_entries_trading_account_id_foreign` foreign key (`trading_account_id`) references `trading_accounts` (`id`) on delete cascade;
alter table `journal_entries` add index `journal_entries_trading_account_id_id_index`(`trading_account_id`, `id`);
create table `expert_advisors` (`id` bigint unsigned not null auto_increment primary key, `trading_account_id` bigint unsigned not null, `instrument_id` bigint unsigned not null, `name` varchar(255) not null, `strategy` varchar(50) not null, `timeframe` varchar(4) not null default 'M5', `volume` decimal(8, 2) not null default '0.1', `params` json null, `state` json null, `magic` bigint unsigned not null, `is_active` tinyint(1) not null default '1', `last_run_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `expert_advisors` add constraint `expert_advisors_trading_account_id_foreign` foreign key (`trading_account_id`) references `trading_accounts` (`id`) on delete cascade;
alter table `expert_advisors` add constraint `expert_advisors_instrument_id_foreign` foreign key (`instrument_id`) references `instruments` (`id`) on delete restrict;
alter table `expert_advisors` add index `expert_advisors_is_active_index`(`is_active`);
alter table `positions` add `expert_advisor_id` bigint unsigned null after `instrument_id`;
alter table `positions` add constraint `positions_expert_advisor_id_foreign` foreign key (`expert_advisor_id`) references `expert_advisors` (`id`) on delete set null;
alter table `positions` add `magic` bigint unsigned null after `expert_advisor_id`;
alter table `expert_advisors` add `stop_loss_pips` int unsigned null after `params`;
alter table `expert_advisors` add `take_profit_pips` int unsigned null after `stop_loss_pips`;
alter table `expert_advisors` add `max_positions` tinyint unsigned not null default '1' after `take_profit_pips`;
alter table `expert_advisors` add `trailing_stop_pips` int unsigned null after `take_profit_pips`;

-- Data
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_06_19_000001_create_instruments_table', 1),
(5, '2026_06_19_000002_create_trading_accounts_table', 1),
(6, '2026_06_19_000003_create_quotes_table', 1),
(7, '2026_06_19_000004_create_positions_table', 1),
(8, '2026_06_19_000005_create_transactions_table', 1),
(9, '2026_06_20_000001_add_specs_to_instruments_table', 1),
(10, '2026_06_20_000002_add_day_open_to_quotes_table', 1),
(11, '2026_06_20_000003_create_ticks_table', 1),
(12, '2026_06_20_000004_create_orders_table', 1),
(13, '2026_06_20_000005_create_candles_table', 1),
(14, '2026_06_20_000006_create_journal_entries_table', 1),
(15, '2026_06_20_000007_create_expert_advisors_table', 1),
(16, '2026_06_20_000008_add_expert_advisor_to_positions_table', 1),
(17, '2026_06_20_000009_add_risk_to_expert_advisors_table', 1),
(18, '2026_06_20_000010_add_trailing_stop_to_expert_advisors_table', 1);
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Demo Trader', 'trader@example.com', NULL, '$2y$12$cBg3Bgwv5aRGKFPSlZuTNOGj7g.rOA6BpBdBgZD1BSA5qaYLTCNJO', NULL, '2026-06-21 13:27:46', '2026-06-21 13:27:46');
INSERT INTO `instruments` (`id`, `symbol`, `base_currency`, `quote_currency`, `description`, `digits`, `pip_size`, `contract_size`, `min_volume`, `max_volume`, `volume_step`, `is_active`, `sort_order`, `created_at`, `updated_at`, `swap_long`, `swap_short`, `stops_level`, `category`) VALUES
(1, 'EURUSD', 'EUR', 'USD', 'Euro vs US Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 0, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.79, -0.21, 0, 'Majors'),
(2, 'GBPUSD', 'GBP', 'USD', 'Great Britain Pound vs US Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 1, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -1.1, -0.35, 0, 'Majors'),
(3, 'USDJPY', 'USD', 'JPY', 'US Dollar vs Japanese Yen', 3, 0.01, 100000, 0.01, 100, 0.01, 1, 2, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.45, -1.2, 0, 'Majors'),
(4, 'USDCHF', 'USD', 'CHF', 'US Dollar vs Swiss Franc', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 3, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.3, -0.8, 0, 'Majors'),
(5, 'AUDUSD', 'AUD', 'USD', 'Australian Dollar vs US Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 4, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.55, -0.18, 0, 'Majors'),
(6, 'USDCAD', 'USD', 'CAD', 'US Dollar vs Canadian Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 5, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.2, -0.7, 0, 'Majors'),
(7, 'NZDUSD', 'NZD', 'USD', 'New Zealand Dollar vs US Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 6, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.5, -0.2, 0, 'Majors'),
(8, 'EURJPY', 'EUR', 'JPY', 'Euro vs Japanese Yen', 3, 0.01, 100000, 0.01, 100, 0.01, 1, 7, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.1, -1.05, 0, 'Minors'),
(9, 'EURGBP', 'EUR', 'GBP', 'Euro vs Great Britain Pound', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 8, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.45, -0.3, 0, 'Minors'),
(10, 'GBPJPY', 'GBP', 'JPY', 'Great Britain Pound vs Japanese Yen', 3, 0.01, 100000, 0.01, 100, 0.01, 1, 9, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.15, -1.3, 0, 'Minors'),
(11, 'AUDJPY', 'AUD', 'JPY', 'Australian Dollar vs Japanese Yen', 3, 0.01, 100000, 0.01, 100, 0.01, 1, 10, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.25, -0.95, 0, 'Minors'),
(12, 'AUDCAD', 'AUD', 'CAD', 'Australian Dollar vs Canadian Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 11, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.4, -0.25, 0, 'Minors'),
(13, 'AUDCHF', 'AUD', 'CHF', 'Australian Dollar vs Swiss Franc', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 12, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.3, -0.35, 0, 'Minors'),
(14, 'AUDNZD', 'AUD', 'NZD', 'Australian Dollar vs New Zealand Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 13, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.35, -0.3, 0, 'Minors'),
(15, 'CADJPY', 'CAD', 'JPY', 'Canadian Dollar vs Japanese Yen', 3, 0.01, 100000, 0.01, 100, 0.01, 1, 14, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.2, -0.9, 0, 'Minors'),
(16, 'CHFJPY', 'CHF', 'JPY', 'Swiss Franc vs Japanese Yen', 3, 0.01, 100000, 0.01, 100, 0.01, 1, 15, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.2, -0.85, 0, 'Minors'),
(17, 'EURAUD', 'EUR', 'AUD', 'Euro vs Australian Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 16, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.6, -0.2, 0, 'Minors'),
(18, 'EURCAD', 'EUR', 'CAD', 'Euro vs Canadian Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 17, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.55, -0.25, 0, 'Minors'),
(19, 'GBPAUD', 'GBP', 'AUD', 'Great Britain Pound vs Australian Dollar', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 18, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -0.7, -0.3, 0, 'Minors'),
(20, 'USDSEK', 'USD', 'SEK', 'US Dollar vs Swedish Krona', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 19, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.5, -2.1, 0, 'Exotics'),
(21, 'USDNOK', 'USD', 'NOK', 'US Dollar vs Norwegian Krone', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 20, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.45, -1.95, 0, 'Exotics'),
(22, 'USDZAR', 'USD', 'ZAR', 'US Dollar vs South African Rand', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 21, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 1.2, -5.5, 0, 'Exotics'),
(23, 'USDMXN', 'USD', 'MXN', 'US Dollar vs Mexican Peso', 5, 0.0001, 100000, 0.01, 100, 0.01, 1, 22, '2026-06-21 13:27:42', '2026-06-21 13:27:42', 0.9, -4.8, 0, 'Exotics'),
(24, 'XAUUSD', 'XAU', 'USD', 'Gold vs US Dollar', 2, 0.01, 100, 0.01, 100, 0.01, 1, 23, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -3.5, -2, 0, 'Metals'),
(25, 'XAGUSD', 'XAG', 'USD', 'Silver vs US Dollar', 3, 0.001, 5000, 0.01, 100, 0.01, 1, 24, '2026-06-21 13:27:42', '2026-06-21 13:27:42', -1.5, -1, 0, 'Metals');
INSERT INTO `trading_accounts` (`id`, `user_id`, `login`, `name`, `type`, `currency`, `leverage`, `balance`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, '6619077', 'Demo Account', 'demo', 'USD', 100, 9997.6, 1, '2026-06-21 13:27:46', '2026-06-21 13:28:21');
INSERT INTO `quotes` (`id`, `instrument_id`, `bid`, `ask`, `quoted_at`, `created_at`, `updated_at`, `day_open`, `day_open_date`) VALUES
(1, 1, 1.08414, 1.08426, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:29:59', 1.0852, '2026-06-21 00:00:00'),
(2, 2, 1.26943, 1.26955, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:29:59', 1.27193, '2026-06-21 00:00:00'),
(3, 3, 156.157, 156.169, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 156.282, '2026-06-21 00:00:00'),
(4, 4, 0.89479, 0.89491, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 0.89389, '2026-06-21 00:00:00'),
(5, 5, 0.66176, 0.66188, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 0.66273, '2026-06-21 00:00:00'),
(6, 6, 1.36871, 1.36883, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 1.37108, '2026-06-21 00:00:00'),
(7, 7, 0.61232, 0.61244, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 0.61217, '2026-06-21 00:00:00'),
(8, 8, 169.669, 169.681, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 169.59, '2026-06-21 00:00:00'),
(9, 9, 0.85181, 0.85193, '2026-06-21 13:29:59', '2026-06-21 13:27:42', '2026-06-21 13:30:00', 0.85277, '2026-06-21 00:00:00'),
(10, 10, 198.586, 198.598, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 198.721, '2026-06-21 00:00:00'),
(11, 11, 103.728, 103.74, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 103.617, '2026-06-21 00:00:00'),
(12, 12, 0.90797, 0.90809, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 0.90884, '2026-06-21 00:00:00'),
(13, 13, 0.59433, 0.59445, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 0.59285, '2026-06-21 00:00:00'),
(14, 14, 1.08303, 1.08315, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 1.08299, '2026-06-21 00:00:00'),
(15, 15, 113.945, 113.957, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 114.019, '2026-06-21 00:00:00'),
(16, 16, 174.73, 174.742, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 174.791, '2026-06-21 00:00:00'),
(17, 17, 1.63678, 1.6369, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 1.63591, '2026-06-21 00:00:00'),
(18, 18, 1.48786, 1.48798, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 1.48705, '2026-06-21 00:00:00'),
(19, 19, 1.91799, 1.91811, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 1.91806, '2026-06-21 00:00:00'),
(20, 20, 10.54903, 10.55303, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 10.55017, '2026-06-21 00:00:00'),
(21, 21, 10.72068, 10.72468, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 10.72017, '2026-06-21 00:00:00'),
(22, 22, 18.24682, 18.25182, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 18.24985, '2026-06-21 00:00:00'),
(23, 23, 18.44828, 18.45328, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 18.45002, '2026-06-21 00:00:00'),
(24, 24, 2329.59, 2329.99, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 2330.01, '2026-06-21 00:00:00'),
(25, 25, 29.508, 29.514, '2026-06-21 13:29:59', '2026-06-21 13:27:43', '2026-06-21 13:30:00', 29.497, '2026-06-21 00:00:00');
INSERT INTO `transactions` (`id`, `trading_account_id`, `position_id`, `type`, `amount`, `balance_after`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'deposit', 10000, 10000, 'Initial demo deposit', '2026-06-21 13:27:46', '2026-06-21 13:27:46'),
(2, 1, 1, 'trade', -1.2, 9998.8, 'Closed #28197019 buy 0.1 EURUSD', '2026-06-21 13:28:21', '2026-06-21 13:28:21'),
(3, 1, 2, 'trade', -1.2, 9997.6, 'Closed #44012031 sell 0.1 GBPUSD', '2026-06-21 13:28:21', '2026-06-21 13:28:21');
INSERT INTO `orders` (`id`, `ticket`, `trading_account_id`, `instrument_id`, `type`, `volume`, `price`, `stop_loss`, `take_profit`, `status`, `position_id`, `expires_at`, `placed_at`, `filled_at`, `created_at`, `updated_at`) VALUES
(1, 72737485, 1, 3, 'sell_stop', 0.1, 155.976, NULL, NULL, 'pending', NULL, NULL, '2026-06-21 13:28:21', NULL, '2026-06-21 13:28:21', '2026-06-21 13:28:21');
INSERT INTO `journal_entries` (`id`, `trading_account_id`, `level`, `category`, `message`, `created_at`, `updated_at`) VALUES
(1, 1, 'success', 'trade', 'Opened #28197019 BUY 0.1 EURUSD at 1.08526', '2026-06-21 13:28:20', '2026-06-21 13:28:20'),
(2, 1, 'success', 'trade', 'Opened #44012031 SELL 0.1 GBPUSD at 1.27187', '2026-06-21 13:28:20', '2026-06-21 13:28:20'),
(3, 1, 'error', 'trade', 'Closed #28197019 EURUSD at 1.08514 — P/L -1.20', '2026-06-21 13:28:21', '2026-06-21 13:28:21'),
(4, 1, 'error', 'trade', 'Closed #44012031 GBPUSD at 1.27199 — P/L -1.20', '2026-06-21 13:28:21', '2026-06-21 13:28:21'),
(5, 1, 'success', 'trade', 'Opened #24354280 BUY 0.2 EURUSD at 1.08526', '2026-06-21 13:28:21', '2026-06-21 13:28:21'),
(6, 1, 'success', 'trade', 'Opened #45163952 SELL 0.05 XAUUSD at 2329.81', '2026-06-21 13:28:21', '2026-06-21 13:28:21'),
(7, 1, 'success', 'trade', 'Opened #86117235 BUY 0.3 AUDUSD at 0.66279', '2026-06-21 13:28:21', '2026-06-21 13:28:21'),
(8, 1, 'info', 'order', 'Placed sell stop #72737485 USDJPY at 155.976', '2026-06-21 13:28:21', '2026-06-21 13:28:21');
INSERT INTO `expert_advisors` (`id`, `trading_account_id`, `instrument_id`, `name`, `strategy`, `timeframe`, `volume`, `params`, `state`, `magic`, `is_active`, `last_run_at`, `created_at`, `updated_at`, `stop_loss_pips`, `take_profit_pips`, `max_positions`, `trailing_stop_pips`) VALUES
(1, 1, 1, 'Moving Average Cross EURUSD', 'ma_cross', 'M5', 0.1, '{"fast":10,"slow":30}', '[]', 2883042, 1, '2026-06-21 13:29:58', '2026-06-21 13:28:21', '2026-06-21 13:29:58', 300, NULL, 1, 150);
INSERT INTO `positions` (`id`, `ticket`, `trading_account_id`, `instrument_id`, `side`, `volume`, `open_price`, `close_price`, `stop_loss`, `take_profit`, `commission`, `swap`, `profit`, `status`, `opened_at`, `closed_at`, `created_at`, `updated_at`, `expert_advisor_id`, `magic`) VALUES
(1, 28197019, 1, 1, 'buy', 0.1, 1.08526, 1.08514, NULL, NULL, 0, 0, -1.2, 'closed', '2026-06-21 13:28:20', '2026-06-21 13:28:21', '2026-06-21 13:28:20', '2026-06-21 13:28:21', NULL, NULL),
(2, 44012031, 1, 2, 'sell', 0.1, 1.27187, 1.27199, NULL, NULL, 0, 0, -1.2, 'closed', '2026-06-21 13:28:20', '2026-06-21 13:28:21', '2026-06-21 13:28:20', '2026-06-21 13:28:21', NULL, NULL),
(3, 24354280, 1, 1, 'buy', 0.2, 1.08526, NULL, NULL, NULL, 0, 0, 0, 'open', '2026-06-21 13:28:21', NULL, '2026-06-21 13:28:21', '2026-06-21 13:28:21', NULL, NULL),
(4, 45163952, 1, 24, 'sell', 0.05, 2329.81, NULL, NULL, NULL, 0, 0, 0, 'open', '2026-06-21 13:28:21', NULL, '2026-06-21 13:28:21', '2026-06-21 13:28:21', NULL, NULL),
(5, 86117235, 1, 5, 'buy', 0.3, 0.66279, NULL, NULL, NULL, 0, 0, 0, 'open', '2026-06-21 13:28:21', NULL, '2026-06-21 13:28:21', '2026-06-21 13:28:21', NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
