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
        Schema::table('unit_processes', function (Blueprint $table) {
            $table->unsignedBigInteger('queue_id');
            $table->foreign('queue_id')->references('id')->on('trade_queue')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_processes', function (Blueprint $table) {
            $table->dropForeign(['queue_id']);
            $table->dropColumn('queue_id');
        });
    }
};
