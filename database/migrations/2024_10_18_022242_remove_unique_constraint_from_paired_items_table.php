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
        Schema::table('paired_items', function (Blueprint $table) {
            $table->dropUnique(['pair_1', 'pair_2']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paired_items', function (Blueprint $table) {
            $table->unique(['pair_1', 'pair_2']);
        });
    }
};
