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
        Schema::create('funder_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->string('name');
            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');
            $table->string('asset_type');
            $table->string('symbol');
            $table->string('current_phase');
            $table->decimal('starting_balance');
            $table->string('drawdown_type');
            $table->decimal('total_target_profit');
            $table->decimal('per_trade_target_profit');
            $table->decimal('daily_target_profit');
            $table->decimal('max_drawdown');
            $table->decimal('per_trade_drawdown');
            $table->decimal('daily_drawdown');
            $table->string('platform_type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funder_packages', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
            $table->dropForeign(['funder_id']);
            $table->dropColumn('funder_id');
        });
        Schema::dropIfExists('funder_packages');
    }
};
