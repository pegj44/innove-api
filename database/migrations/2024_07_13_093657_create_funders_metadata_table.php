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
        Schema::create('funders_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');
            $table->string('key');
            $table->longText('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funders_metadata', function (Blueprint $table) {
            $table->dropForeign(['funder_id']);
            $table->dropColumn('funder_id');
        });
        Schema::dropIfExists('funders_metadata');
    }
};
