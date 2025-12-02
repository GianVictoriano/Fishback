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
        Schema::create('literary_work_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('literary_work_id')
                  ->constrained('literary_works')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('heart_count')->default(0);
            $table->unsignedBigInteger('sad_count')->default(0);
            $table->unsignedBigInteger('wow_count')->default(0);
            $table->timestamps();

            // Unique constraint to ensure one metrics record per literary work
            $table->unique('literary_work_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('literary_work_metrics');
    }
};
