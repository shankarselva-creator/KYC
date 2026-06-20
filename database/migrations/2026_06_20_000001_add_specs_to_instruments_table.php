<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            // Swap (rollover) charged per lot held overnight, in the instrument's
            // points; long/short can differ. Stored signed (credit + / debit -).
            $table->decimal('swap_long', 10, 2)->default(0)->after('volume_step');
            $table->decimal('swap_short', 10, 2)->default(0)->after('swap_long');
            // Minimum distance (in points) for SL/TP and pending orders.
            $table->unsignedInteger('stops_level')->default(0)->after('swap_short');
            // Free-form grouping for the Market Watch (e.g. "Majors", "Metals").
            $table->string('category', 40)->default('Forex')->after('stops_level');
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            $table->dropColumn(['swap_long', 'swap_short', 'stops_level', 'category']);
        });
    }
};
