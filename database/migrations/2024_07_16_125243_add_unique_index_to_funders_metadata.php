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
        Schema::table('funders_metadata', function (Blueprint $table) {
            $table->unique(['funder_id', 'key'], 'unique_funder_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funders_metadata', function (Blueprint $table) {
            $table->dropUnique('unique_funder_key');
        });
    }
};
