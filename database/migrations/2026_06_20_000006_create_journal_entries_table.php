<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_account_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['info', 'success', 'warn', 'error'])->default('info');
            $table->string('category', 30)->default('trade');  // trade | order | funding | system
            $table->string('message');
            $table->timestamps();

            $table->index(['trading_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
