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
        Schema::create('creative_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')
                  ->constrained('creatives')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('heart_count')->default(0);
            $table->unsignedBigInteger('sad_count')->default(0);
            $table->unsignedBigInteger('wow_count')->default(0);
            $table->timestamps();

            // Indexes
            $table->unique('creative_id');
            $table->index('creative_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_metrics');
    }
};
