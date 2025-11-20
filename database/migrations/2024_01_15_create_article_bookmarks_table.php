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
        Schema::create('article_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->text('highlighted_text'); // The actual text that was highlighted
            $table->integer('start_offset')->nullable(); // Character offset where highlight starts
            $table->integer('end_offset')->nullable(); // Character offset where highlight ends
            $table->string('context_before', 100)->nullable(); // Text before highlight for context
            $table->string('context_after', 100)->nullable(); // Text after highlight for context
            $table->text('notes')->nullable(); // Optional user notes
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['user_id', 'article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_bookmarks');
    }
};
