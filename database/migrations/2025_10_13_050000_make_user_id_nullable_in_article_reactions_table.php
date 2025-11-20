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
        Schema::table('article_reactions', function (Blueprint $table) {
            // Change the user_id column to be nullable
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_reactions', function (Blueprint $table) {
            // Revert the change, making it non-nullable again
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
