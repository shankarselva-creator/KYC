<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->decimal('bid', 18, 8);
            $table->decimal('ask', 18, 8);
            $table->timestamp('tick_at');

            // Rolling history is pruned by QuoteService; index for fast tail reads.
            $table->index(['instrument_id', 'tick_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticks');
    }
};
