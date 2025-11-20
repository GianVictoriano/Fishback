<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('article_reactions', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('reaction_type');
            }

            // Composite unique keys to prevent duplicate reactions
            $table->unique(['user_id', 'article_id'], 'article_reactions_user_unique');
            $table->unique(['ip_address', 'article_id'], 'article_reactions_ip_unique');
        });
    }

    public function down(): void
    {
        Schema::table('article_reactions', function (Blueprint $table) {
            $table->dropUnique('article_reactions_user_unique');
            $table->dropUnique('article_reactions_ip_unique');
            if (Schema::hasColumn('article_reactions', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
        });
    }
};
