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
        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->string('instagram_business_account_id')->nullable();
            $table->string('facebook_page_id')->nullable();
            $table->text('facebook_page_access_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->dropColumn(['instagram_business_account_id', 'facebook_page_id', 'facebook_page_access_token']);
        });
    }
};
