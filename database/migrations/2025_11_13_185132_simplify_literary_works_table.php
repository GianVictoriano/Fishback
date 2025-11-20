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
        Schema::table('literary_works', function (Blueprint $table) {
            // Remove PDF-related fields
            $table->dropColumn('pdf_path');
            $table->dropColumn('heyzine_id');
            
            // Update status enum to remove unnecessary statuses
            $table->enum('status', ['draft', 'published'])->default('published')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('literary_works', function (Blueprint $table) {
            // Add back the removed fields
            $table->string('pdf_path')->nullable();
            $table->string('heyzine_id')->nullable();
            
            // Restore original status enum
            $table->enum('status', ['draft', 'processing', 'ready', 'published'])->default('draft')->change();
        });
    }
};
