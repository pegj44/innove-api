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
        Schema::table('trading_individual_metadata', function (Blueprint $table) {
            $table->unique(['trading_individual_id', 'key'], 'unique_trading_individual_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_individual_metadata', function (Blueprint $table) {
            $table->dropUnique('unique_trading_individual_key');
        });
    }
};
