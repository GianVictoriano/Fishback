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
        Schema::create('scrum_boards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('category', ['Sports', 'Literature', 'Technology', 'Art', 'Science', 'Other'])->default('Other');
            $table->json('collaborators')->nullable(); // Store as array of user IDs
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->dateTime('deadline')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrum_boards');
    }
};
