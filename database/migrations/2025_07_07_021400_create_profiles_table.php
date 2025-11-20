<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link to users table
            $table->enum('role', ['user', 'adviser', 'collaborator','editor','admin'])->default('user');
            $table->string('avatar')->nullable(); // You can store a file path or URL
            $table->string('name')->nullable();
            $table->string('program')->nullable();
            $table->string('section')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
