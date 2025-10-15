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
        // Add is_folio_submission flag to review_content table
        Schema::table('review_content', function (Blueprint $table) {
            $table->boolean('is_folio_submission')->default(false)->after('status');
            $table->foreignId('folio_id')->nullable()->after('is_folio_submission')->constrained('folios')->onDelete('cascade');
        });

        // Add is_folio_submission flag to review_images table
        Schema::table('review_images', function (Blueprint $table) {
            $table->boolean('is_folio_submission')->default(false)->after('status');
            $table->foreignId('folio_id')->nullable()->after('is_folio_submission')->constrained('folios')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('review_content', function (Blueprint $table) {
            $table->dropForeign(['folio_id']);
            $table->dropColumn(['is_folio_submission', 'folio_id']);
        });

        Schema::table('review_images', function (Blueprint $table) {
            $table->dropForeign(['folio_id']);
            $table->dropColumn(['is_folio_submission', 'folio_id']);
        });
    }
};
