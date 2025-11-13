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
        Schema::table('review_comments', function (Blueprint $table) {
            $table->foreignId('review_image_id')->nullable()->after('review_content_id')->constrained('review_images')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_comments', function (Blueprint $table) {
            $table->dropForeign(['review_image_id']);
            $table->dropColumn('review_image_id');
        });
    }
};
