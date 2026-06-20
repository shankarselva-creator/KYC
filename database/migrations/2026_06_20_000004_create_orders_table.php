<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket')->unique();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
            // Pending order types only; market fills become Positions directly.
            $table->enum('type', ['buy_limit', 'sell_limit', 'buy_stop', 'sell_stop']);
            $table->decimal('volume', 8, 2);
            $table->decimal('price', 18, 8);              // trigger price
            $table->decimal('stop_loss', 18, 8)->nullable();
            $table->decimal('take_profit', 18, 8)->nullable();
            $table->enum('status', ['pending', 'filled', 'cancelled', 'expired'])->default('pending');
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->index(['trading_account_id', 'status']);
            $table->index(['status', 'instrument_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
