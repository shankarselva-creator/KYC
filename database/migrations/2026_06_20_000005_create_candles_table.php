<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('timeframe', 4);              // M1, M5, M15, M30, H1, H4, D1, W1, MN
            $table->timestamp('opened_at');              // bucket start (UTC)
            $table->decimal('open', 18, 8);
            $table->decimal('high', 18, 8);
            $table->decimal('low', 18, 8);
            $table->decimal('close', 18, 8);
            $table->unsignedInteger('volume')->default(0);
            $table->timestamps();

            $table->unique(['instrument_id', 'timeframe', 'opened_at']);
            $table->index(['instrument_id', 'timeframe', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};
