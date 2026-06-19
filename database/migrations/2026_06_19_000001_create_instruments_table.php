<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();        // e.g. EURUSD
            $table->string('base_currency', 10);           // EUR
            $table->string('quote_currency', 10);          // USD
            $table->string('description')->nullable();     // Euro vs US Dollar
            $table->unsignedTinyInteger('digits')->default(5);
            $table->decimal('pip_size', 12, 8)->default(0.0001);
            $table->unsignedInteger('contract_size')->default(100000);
            $table->decimal('min_volume', 8, 2)->default(0.01);
            $table->decimal('max_volume', 8, 2)->default(100.00);
            $table->decimal('volume_step', 8, 2)->default(0.01);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
