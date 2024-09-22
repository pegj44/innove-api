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
        Schema::table('trade_reports', function (Blueprint $table) {
            $table->string('order_type');
            $table->string('order_amount');
            $table->string('stop_loss_ticks');
            $table->string('take_profit_ticks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_reports', function (Blueprint $table) {
            //
        });
    }
};
