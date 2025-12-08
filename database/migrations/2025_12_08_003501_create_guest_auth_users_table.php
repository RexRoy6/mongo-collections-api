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
    Schema::create('guest_auth_users', function (Blueprint $table) {
        $table->id();
        $table->string('guest_uuid')->unique();
        $table->string('guest_name');
        $table->integer('room_number');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_auth_users');
    }
};
