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
        Schema::create('trade_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
//            $table->unsignedBigInteger('trading_unit_id');
//            $table->foreign('trading_unit_id')->references('id')->on('trading_units');
//            $table->unsignedBigInteger('funder_id');
//            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');
            $table->unsignedBigInteger('trade_account_credential_id');
            $table->foreign('trade_account_credential_id')->references('id')->on('trading_account_credentials')->onDelete('cascade');
            $table->decimal('starting_balance');
            $table->decimal('starting_equity');
            $table->decimal('latest_equity');
            $table->string('status');
            $table->longText('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_reports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['trading_unit_id']);
            $table->dropColumn('trading_unit_id');
            $table->dropForeign(['funder_id']);
            $table->dropColumn('funder_id');
            $table->dropForeign(['trade_account_credential_id']);
            $table->dropColumn('trade_account_credential_id');
        });
        Schema::dropIfExists('trade_reports');
    }
};
