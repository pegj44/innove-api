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
        Schema::create('trading_individual_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trading_individual_id');
            $table->foreign('trading_individual_id')->references('id')->on('trading_individuals')->onDelete('cascade');
            $table->string('key');
            $table->string('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trading_individual_metadata', function (Blueprint $table) {
            $table->dropForeign(['trading_individual_id']);
            $table->dropColumn('trading_individual_id');
        });
        Schema::dropIfExists('trading_individual_metadata');
    }
};
