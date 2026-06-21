<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'trade', 'commission', 'swap', 'adjustment']);
            $table->decimal('amount', 18, 2);          // signed: credit (+) / debit (-)
            $table->decimal('balance_after', 18, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['trading_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
