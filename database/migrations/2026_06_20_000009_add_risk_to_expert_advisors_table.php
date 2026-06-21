<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_advisors', function (Blueprint $table) {
            $table->unsignedInteger('stop_loss_pips')->nullable()->after('params');
            $table->unsignedInteger('take_profit_pips')->nullable()->after('stop_loss_pips');
            $table->unsignedTinyInteger('max_positions')->default(1)->after('take_profit_pips');
        });
    }

    public function down(): void
    {
        Schema::table('expert_advisors', function (Blueprint $table) {
            $table->dropColumn(['stop_loss_pips', 'take_profit_pips', 'max_positions']);
        });
    }
};
