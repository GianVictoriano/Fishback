<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('article_reactions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('article_reactions', 'article_id')) {
                $table->foreignId('article_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (!Schema::hasColumn('article_reactions', 'reaction_type')) {
                $table->enum('reaction_type', ['like', 'heart', 'sad', 'wow'])->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('article_reactions', function (Blueprint $table) {
            if (Schema::hasColumn('article_reactions', 'reaction_type')) {
                $table->dropColumn('reaction_type');
            }
            if (Schema::hasColumn('article_reactions', 'article_id')) {
                $table->dropColumn('article_id');
            }
            if (Schema::hasColumn('article_reactions', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};
