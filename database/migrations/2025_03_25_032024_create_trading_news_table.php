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
        Schema::create('trading_news', function (Blueprint $table) {
            $table->id();
            $table->string('country')->nullable();
            $table->string('impact')->nullable();
            $table->string('title')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_news');
    }
};
