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
        Schema::table('trading_account_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('funder_package_id')->nullable();
            $table->foreign('funder_package_id')->references('id')->on('funder_packages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_account_credentials', function (Blueprint $table) {
            $table->dropForeign(['funder_package_id']);
            $table->dropColumn('funder_package_id');
        });
    }
};
