<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ExpertAdvisorController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\InstrumentController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('terminal'));

// ---------------------------------------------------------------------------
// Guest auth routes
// ---------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// ---------------------------------------------------------------------------
// Authenticated routes
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/terminal', [DashboardController::class, 'terminal'])->name('terminal');
    Route::get('/history', [DashboardController::class, 'history'])->name('history');

    // JSON API consumed by the web terminal (session-authenticated, CSRF-protected).
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes');
        Route::get('/instruments/{symbol}/specification', [InstrumentController::class, 'specification'])->name('instruments.specification');
        Route::get('/instruments/{symbol}/ticks', [InstrumentController::class, 'ticks'])->name('instruments.ticks');
        Route::get('/instruments/{symbol}/candles', [InstrumentController::class, 'candles'])->name('instruments.candles');
        Route::get('/account', [AccountController::class, 'show'])->name('account');
        Route::post('/account/deposit', [AccountController::class, 'deposit'])->name('account.deposit');
        Route::post('/account/withdraw', [AccountController::class, 'withdraw'])->name('account.withdraw');
        Route::get('/positions', [PositionController::class, 'index'])->name('positions');
        Route::get('/history', [HistoryController::class, 'show'])->name('history');
        Route::get('/journal', [JournalController::class, 'index'])->name('journal');

        // Expert Advisors (automated strategies)
        Route::get('/experts/strategies', [ExpertAdvisorController::class, 'strategies'])->name('experts.strategies');
        Route::get('/experts', [ExpertAdvisorController::class, 'index'])->name('experts.index');
        Route::post('/experts', [ExpertAdvisorController::class, 'store'])->name('experts.store');
        Route::post('/experts/{expert}/toggle', [ExpertAdvisorController::class, 'toggle'])->name('experts.toggle');
        Route::delete('/experts/{expert}', [ExpertAdvisorController::class, 'destroy'])->name('experts.destroy');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/positions/{position}/close', [PositionController::class, 'close'])->name('positions.close');
        Route::post('/positions/{position}/modify', [PositionController::class, 'modify'])->name('positions.modify');
    });
});
