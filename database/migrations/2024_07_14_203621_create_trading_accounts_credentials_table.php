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
        Schema::create('trading_accounts_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('funder_id');
            $table->foreign('funder_id')->references('id')->on('funders')->onDelete('cascade');
            $table->bigInteger('account_id');
//            $table->string('dashboard_url');
//            $table->string('dashboard_password');

//            $table->string('dashboard_password');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_accounts_credentials');
    }
};
