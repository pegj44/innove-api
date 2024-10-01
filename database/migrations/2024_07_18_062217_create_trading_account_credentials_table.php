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
        Schema::create('trading_account_credentials', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $table->unsignedBigInteger('user_account_id');
            $table->foreign('user_account_id')->references('id')->on('trading_individuals')->onDelete('cascade');

            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');

            $table->string('funder_account_id');
            $table->string('starting_balance');
            $table->string('asset_type');
            $table->string('symbol');
            $table->string('current_phase');

            $table->string('phase_1_total_target_profit');
            $table->string('phase_1_daily_target_profit');
            $table->string('phase_1_max_drawdown');
            $table->string('phase_1_daily_drawdown');

            $table->string('phase_2_total_target_profit');
            $table->string('phase_2_daily_target_profit');
            $table->string('phase_2_max_drawdown');
            $table->string('phase_2_daily_drawdown');

            $table->string('phase_3_total_target_profit');
            $table->string('phase_3_daily_target_profit');
            $table->string('phase_3_max_drawdown');
            $table->string('phase_3_daily_drawdown');

            $table->string('status')->nullable();

            $table->string('platform_login_username')->nullable();
            $table->string('platform_login_password')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_account_credentials', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
            $table->dropForeign(['user_account_id']);
            $table->dropColumn('user_account_id');
            $table->dropForeign(['funder_id']);
            $table->dropColumn('funder_id');
        });
        Schema::dropIfExists('trading_account_credentials');
    }
};
