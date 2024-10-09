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
        Schema::create('funder_account_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->unsignedBigInteger('trading_individual_id');
            $table->foreign('trading_individual_id')->references('id')->on('trading_individuals');
            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders');
            $table->string('platform_login_username');
            $table->string('platform_login_password');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::table('funder_account_credentials', function (Blueprint $table) {
//            $table->dropForeign(['account_id']);
//            $table->dropColumn('account_id');
//            $table->dropForeign(['trading_individual_id']);
//            $table->dropColumn('trading_individual_id');
//            $table->dropForeign(['funder_id']);
//            $table->dropColumn('funder_id');
//        });
        Schema::dropIfExists('funder_account_credentials');
    }
};
