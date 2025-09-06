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
            $table->boolean('is_story')->default(false)->after('post_type');
            $table->json('story_stickers')->nullable()->after('is_story'); // For story stickers, polls, etc.
            $table->integer('story_duration')->default(15)->after('story_stickers'); // Story duration in seconds
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instagram_posts', function (Blueprint $table) {
            $table->dropColumn(['is_story', 'story_stickers', 'story_duration']);
        });
    }
};
