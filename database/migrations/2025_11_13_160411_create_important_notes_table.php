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
        Schema::create('important_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_chat_id')->constrained('group_chats')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->decimal('version', 8, 1)->nullable();
            $table->string('versionable_type')->nullable(); // 'App\Models\ReviewContent' or 'App\Models\ReviewImage'
            $table->unsignedBigInteger('versionable_id')->nullable(); // ID of the review_content or review_image
            $table->timestamps();

            $table->index(['group_chat_id', 'is_active']);
            $table->index(['user_id', 'created_at']);
            $table->index(['versionable_type', 'versionable_id']);
            $table->index(['group_chat_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('important_notes');
    }
};
