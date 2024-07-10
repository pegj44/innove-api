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
        Schema::create('trading_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('trading_unit_id');
            $table->foreign('trading_unit_id')->references('id')->on('trading_units');
            $table->string('funder');
            $table->string('account');
            $table->string('initial');
            $table->integer('phase');
            $table->decimal('starting_balance');
            $table->decimal('starting_daily_equity');
            $table->decimal('latest_equity');
            $table->string('status');
            $table->decimal('target_profit');
            $table->string('remarks');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['trading_unit_id']);
            $table->dropColumn('trading_unit_id');
        });
        Schema::dropIfExists('trading_accounts');
    }
};
