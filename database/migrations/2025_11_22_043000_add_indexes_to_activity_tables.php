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
        // Add indexes to article_reactions table for faster queries
        Schema::table('article_reactions', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_id');
            $table->index('article_id');
            $table->index(['user_id', 'created_at']);
            $table->index(['article_id', 'created_at']);
        });

        // Add indexes to applicants table
        Schema::table('applicants', function (Blueprint $table) {
            $table->index('created_at');
        });

        // Add indexes to chat_messages table (for when user filter includes them)
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
        });

        // Add indexes to comments table (for when user filter includes them)
        Schema::table('comments', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from article_reactions table
        Schema::table('article_reactions', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['article_id']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['article_id', 'created_at']);
        });

        // Remove indexes from applicants table
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        // Remove indexes from chat_messages table
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        // Remove indexes from comments table
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
