<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('expert_advisor_id')->nullable()->after('instrument_id')
                ->constrained()->nullOnDelete();
            $table->unsignedBigInteger('magic')->nullable()->after('expert_advisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expert_advisor_id');
            $table->dropColumn('magic');
        });
    }
};
