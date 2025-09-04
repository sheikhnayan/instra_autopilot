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
            $table->string('instagram_user_id')->nullable();
            $table->string('account_type')->nullable();
            $table->integer('media_count')->default(0);
            $table->timestamp('last_sync_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instagram_accounts', function (Blueprint $table) {
            $table->dropColumn(['instagram_user_id', 'account_type', 'media_count', 'last_sync_at']);
        });
    }
};
