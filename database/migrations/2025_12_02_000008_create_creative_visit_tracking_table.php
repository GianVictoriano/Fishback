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
        Schema::create('creative_visit_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creative_id')
                  ->constrained('creatives')
                  ->cascadeOnDelete();
            $table->string('ip_address');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('visited_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['creative_id', 'ip_address']);
            $table->index(['creative_id', 'visited_at']);
            $table->unique(['creative_id', 'ip_address', 'visited_at'], 'unique_visit_per_ip_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_visit_tracking');
    }
};
