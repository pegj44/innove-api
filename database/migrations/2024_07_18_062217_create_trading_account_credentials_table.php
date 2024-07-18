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
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('trading_individual_id');
            $table->foreign('trading_individual_id')->references('id')->on('trading_individuals')->onDelete('cascade');
            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');
            $table->string('dashboard_login_url');
            $table->string('dashboard_login_username');
            $table->string('dashboard_login_password');
            $table->string('platform_login_url');
            $table->string('platform_login_username');
            $table->string('platform_login_password');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_account_credentials', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['funder_id']);
            $table->dropColumn('funder_id');
            $table->dropForeign(['trading_individual_id']);
            $table->dropColumn('trading_individual_id');
        });
        Schema::dropIfExists('trading_account_credentials');
    }
};
