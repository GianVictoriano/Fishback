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
        Schema::table('plagiarism_results', function (Blueprint $table) {
            $table->decimal('score', 5, 2)->nullable()->after('status');
            $table->decimal('ai_score', 5, 2)->nullable()->after('score');
            $table->json('analysis')->nullable()->after('ai_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plagiarism_results', function (Blueprint $table) {
            $table->dropColumn(['score', 'ai_score', 'analysis']);
        });
    }
};
