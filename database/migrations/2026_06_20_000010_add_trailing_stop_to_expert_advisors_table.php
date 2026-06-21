<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_advisors', function (Blueprint $table) {
            $table->unsignedInteger('trailing_stop_pips')->nullable()->after('take_profit_pips');
        });
    }

    public function down(): void
    {
        Schema::table('expert_advisors', function (Blueprint $table) {
            $table->dropColumn('trailing_stop_pips');
        });
    }
};
