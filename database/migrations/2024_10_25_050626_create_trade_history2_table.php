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
        Schema::create('trade_history2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->unsignedBigInteger('trade_account_credential_id');
            $table->foreign('trade_account_credential_id')->references('id')->on('trading_account_credentials')->onDelete('cascade');
            $table->decimal('starting_daily_equity');
            $table->decimal('latest_equity');
            $table->decimal('highest_equity');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trade_history2', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
            $table->dropForeign(['trade_account_credential_id']);
            $table->dropColumn('trade_account_credential_id');
        });
        Schema::dropIfExists('trade_history2');
    }
};
