<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // Reference mid-price for the current trading day, used to compute the
            // Market Watch "Daily Change %". Reset on the first tick of a new day.
            $table->decimal('day_open', 18, 8)->nullable()->after('ask');
            $table->date('day_open_date')->nullable()->after('day_open');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['day_open', 'day_open_date']);
        });
    }
};
