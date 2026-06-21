<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('login', 20)->unique();         // MT-style account number
            $table->string('name')->nullable();
            $table->enum('type', ['demo', 'live'])->default('demo');
            $table->string('currency', 10)->default('USD');
            $table->unsignedInteger('leverage')->default(100);
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_accounts');
    }
};
