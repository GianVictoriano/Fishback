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
        Schema::create('review_content', function (Blueprint $table) {
            $table->id();
            $table->string('file'); // file path or name
            $table->foreignId('group_id')->constrained('group_chats')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->unsignedInteger('no_of_approval')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_content');
    }
};
