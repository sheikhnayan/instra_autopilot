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
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->text('error_message')->nullable();
            $table->string('image_path')->nullable(); // Single image path for simplified handling
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropColumn(['error_message', 'image_path']);
        });
    }
};
