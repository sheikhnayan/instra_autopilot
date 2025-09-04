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
        Schema::create('instagram_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_container_id')->constrained('content_containers')->onDelete('cascade');
            $table->text('caption'); // Post content/caption
            $table->json('images'); // Array of image paths
            $table->json('hashtags')->nullable(); // Array of hashtags
            $table->string('post_type')->default('photo'); // photo, video, carousel
            $table->integer('order')->default(0); // Order within container
            $table->enum('status', ['draft', 'scheduled', 'posted', 'failed'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->string('instagram_post_id')->nullable(); // Instagram's post ID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instagram_posts');
    }
};
