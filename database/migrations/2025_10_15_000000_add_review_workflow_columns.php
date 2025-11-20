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
        // Add lead_reviewer_id to scrum_boards table
        Schema::table('scrum_boards', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_reviewer_id')->nullable()->after('deadline');
            $table->foreign('lead_reviewer_id')->references('id')->on('users')->onDelete('set null');
        });

        // Add review workflow columns to review_content table
        Schema::table('review_content', function (Blueprint $table) {
            $table->unsignedBigInteger('current_reviewer_id')->nullable()->after('user_id');
            $table->string('review_stage', 50)->default('initial')->after('current_reviewer_id');
            $table->foreign('current_reviewer_id')->references('id')->on('users')->onDelete('set null');
            
            // Add index for better query performance
            $table->index(['current_reviewer_id', 'review_stage']);
        });

        // Add review workflow columns to review_images table
        Schema::table('review_images', function (Blueprint $table) {
            $table->unsignedBigInteger('current_reviewer_id')->nullable()->after('user_id');
            $table->string('review_stage', 50)->default('initial')->after('current_reviewer_id');
            $table->foreign('current_reviewer_id')->references('id')->on('users')->onDelete('set null');
            
            // Add index for better query performance
            $table->index(['current_reviewer_id', 'review_stage']);
        });

        // Create review_history table for tracking review chain (optional but recommended)
        Schema::create('review_history', function (Blueprint $table) {
            $table->id();
            $table->string('reviewable_type'); // 'review_content' or 'review_images'
            $table->unsignedBigInteger('reviewable_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('action', 50); // 'submitted', 'approved', 'rejected', 'forwarded'
            $table->unsignedBigInteger('forwarded_to_id')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('reviewer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('forwarded_to_id')->references('id')->on('users')->onDelete('set null');
            
            // Add index for polymorphic relationship
            $table->index(['reviewable_type', 'reviewable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_history');
        
        Schema::table('review_images', function (Blueprint $table) {
            $table->dropForeign(['current_reviewer_id']);
            $table->dropIndex(['current_reviewer_id', 'review_stage']);
            $table->dropColumn(['current_reviewer_id', 'review_stage']);
        });

        Schema::table('review_content', function (Blueprint $table) {
            $table->dropForeign(['current_reviewer_id']);
            $table->dropIndex(['current_reviewer_id', 'review_stage']);
            $table->dropColumn(['current_reviewer_id', 'review_stage']);
        });

        Schema::table('scrum_boards', function (Blueprint $table) {
            $table->dropForeign(['lead_reviewer_id']);
            $table->dropColumn('lead_reviewer_id');
        });
    }
};
