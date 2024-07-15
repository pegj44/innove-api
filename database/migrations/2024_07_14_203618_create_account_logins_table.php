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
        Schema::create('account_logins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trading_individual_id');
            $table->foreign('trading_individual_id')->references('id')->on('trading_individuals')->onDelete('cascade');
            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');
            $table->string('title');
            $table->string('url');
            $table->string('password');
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_logins', function (Blueprint $table) {
            $table->dropForeign(['funder_id']);
            $table->dropColumn('funder_id');
            $table->dropForeign(['trading_individual_id']);
            $table->dropColumn('trading_individual_id');
        });
        Schema::dropIfExists('account_logins');
    }
};
