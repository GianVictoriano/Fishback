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
        Schema::table('important_notes', function (Blueprint $table) {
            $table->decimal('version', 8, 1)->nullable()->after('is_active');
            $table->string('versionable_type')->nullable()->after('version'); // 'App\Models\ReviewContent' or 'App\Models\ReviewImage'
            $table->unsignedBigInteger('versionable_id')->nullable()->after('versionable_type'); // ID of the review_content or review_image

            // Add indexes for performance
            $table->index(['versionable_type', 'versionable_id']);
            $table->index(['group_chat_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('important_notes', function (Blueprint $table) {
            $table->dropIndex(['versionable_type', 'versionable_id']);
            $table->dropIndex(['group_chat_id', 'version']);
            $table->dropColumn(['version', 'versionable_type', 'versionable_id']);
        });
    }
};
