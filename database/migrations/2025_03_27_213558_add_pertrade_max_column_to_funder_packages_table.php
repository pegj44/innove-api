<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('funder_packages', function (Blueprint $table) {
            $table->decimal('max_per_trade_target_profit');
            $table->decimal('max_per_trade_drawdown');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funder_packages', function (Blueprint $table) {
            //
        });
    }
};
