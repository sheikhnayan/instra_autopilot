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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_container_id')->constrained('content_containers')->onDelete('cascade');
            $table->foreignId('instagram_account_id')->constrained('instagram_accounts')->onDelete('cascade');
            $table->string('name'); // Schedule name
            $table->date('start_date'); // When to start posting
            $table->time('start_time')->nullable(); // Time to start posting
            $table->integer('interval_minutes'); // Interval between posts (60 mins, 120 mins, etc.)
            $table->enum('status', ['active', 'paused', 'completed', 'stopped'])->default('active');
            $table->timestamp('last_posted_at')->nullable();
            $table->boolean('repeat_cycle')->default(true); // Whether to repeat when all posts are done
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
