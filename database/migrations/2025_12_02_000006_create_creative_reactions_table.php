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
        Schema::create('creative_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('creative_id')->constrained('creatives')->onDelete('cascade');
            $table->enum('reaction_type', ['like', 'heart', 'sad', 'wow']);
            $table->string('ip_address')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['creative_id', 'user_id']);
            $table->index(['creative_id', 'ip_address']);
            $table->index('creative_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_reactions');
    }
};
