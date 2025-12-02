<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table tracks user interactions with literary works including:
     * - Views
     * - Time spent reading
     * - Scroll depth (how far through the flipbook user scrolled)
     * - Session tracking for grouping related interactions
     */
    public function up(): void
    {
        Schema::create('literary_work_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('literary_work_id')
                  ->constrained('literary_works')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('interaction_type', ['view', 'time_spent', 'scroll']);
            $table->unsignedInteger('time_spent')->nullable(); // in seconds
            $table->unsignedTinyInteger('scroll_percentage')->nullable(); // 0-100
            $table->string('session_id')->nullable(); // Group related interactions
            $table->string('ip_address')->nullable();
            $table->timestamps();

            // Indexes for performance (short names to avoid 64-char limit)
            $table->index(['literary_work_id', 'interaction_type'], 'lw_work_type_idx');
            $table->index(['user_id', 'interaction_type'], 'lw_user_type_idx');
            $table->index('session_id', 'lw_session_idx');
            $table->index('created_at', 'lw_created_idx');

            // Composite index for analytics queries
            $table->index(['literary_work_id', 'user_id', 'created_at'], 'lw_work_user_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literary_work_interactions');
    }
};
