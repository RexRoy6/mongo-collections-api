<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
{
    Schema::create('kitchen_auth_users', function (Blueprint $table) {
        $table->id();
        $table->string('kitchenUser_uuid')->unique();
        $table->string('name_kitchenUser');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_auth_users');
    }
};
