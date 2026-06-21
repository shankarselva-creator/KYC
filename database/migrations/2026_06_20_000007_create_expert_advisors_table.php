<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_advisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('strategy', 50);              // strategy key (ma_cross, rsi_reversion, …)
            $table->string('timeframe', 4)->default('M5');
            $table->decimal('volume', 8, 2)->default(0.10);
            $table->json('params')->nullable();          // strategy parameters
            $table->json('state')->nullable();           // runtime state between ticks
            $table->unsignedBigInteger('magic');         // tags positions owned by this EA
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_advisors');
    }
};
