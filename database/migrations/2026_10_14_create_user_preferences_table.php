<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->string('genre');
            $table->enum('interaction_type', ['view', 'like', 'heart', 'sad', 'wow', 'time_spent', 'scroll']);
            $table->decimal('interaction_weight', 5, 2)->default(1.0);
            $table->integer('time_spent')->default(0); // seconds
            $table->decimal('scroll_percentage', 5, 2)->default(0); // 0-100
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'genre']);
            $table->index(['user_id', 'created_at']);
            $table->index(['article_id', 'interaction_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_preferences');
    }
};
