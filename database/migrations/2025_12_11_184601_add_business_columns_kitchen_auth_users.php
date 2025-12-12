<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

        public function up(): void
    {
        Schema::table('kitchen_auth_users', function (Blueprint $table) {
            if (!Schema::hasColumn('kitchen_auth_users', 'business_uuid')) {
                $table->string('business_uuid')->nullable();
            }

            if (!Schema::hasColumn('kitchen_auth_users', 'business_key')) {
                $table->string('business_key')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_auth_users', function (Blueprint $table) {
            if (Schema::hasColumn('kitchen_auth_users', 'business_uuid')) {
                $table->dropColumn('business_uuid');
            }
             if (Schema::hasColumn('kitchen_auth_users', 'business_key')) {
                $table->dropColumn('business_key');
            }
        });
    }
};