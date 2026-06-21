<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket')->unique();   // MT-style ticket number
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
            $table->enum('side', ['buy', 'sell']);
            $table->decimal('volume', 8, 2);                  // lots
            $table->decimal('open_price', 18, 8);
            $table->decimal('close_price', 18, 8)->nullable();
            $table->decimal('stop_loss', 18, 8)->nullable();
            $table->decimal('take_profit', 18, 8)->nullable();
            $table->decimal('commission', 18, 2)->default(0);
            $table->decimal('swap', 18, 2)->default(0);
            $table->decimal('profit', 18, 2)->default(0);     // realised P/L (set on close)
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['trading_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
