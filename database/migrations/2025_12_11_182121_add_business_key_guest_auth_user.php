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
        Schema::table('guest_auth_users', function (Blueprint $table) {
            if (!Schema::hasColumn('guest_auth_users', 'business_uuid')) {
                $table->string('business_uuid')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_auth_users', function (Blueprint $table) {
            if (Schema::hasColumn('guest_auth_users', 'business_uuid')) {
                $table->dropColumn('business_uuid');
            }
        });
    }
};