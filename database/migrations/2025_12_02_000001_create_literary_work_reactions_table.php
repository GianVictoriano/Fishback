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
        Schema::create('literary_work_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('literary_work_id')
                  ->constrained('literary_works')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('reaction_type', ['like', 'heart', 'sad', 'wow']);
            $table->string('ip_address')->nullable();
            $table->timestamps();

            // Indexes for performance (short names to avoid 64-char limit)
            $table->index(['literary_work_id', 'reaction_type'], 'lwr_work_type_idx');
            $table->index('user_id', 'lwr_user_idx');

            // Prevent duplicate reactions from same user
            $table->unique(['literary_work_id', 'user_id', 'reaction_type'], 'lwr_work_user_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literary_work_reactions');
    }
};
