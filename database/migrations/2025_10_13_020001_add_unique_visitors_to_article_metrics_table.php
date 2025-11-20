<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_metrics', function (Blueprint $table) {
            if (!Schema::hasColumn('article_metrics', 'visitor_ips')) {
                $table->json('visitor_ips')->nullable()->after('visits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('article_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('article_metrics', 'visitor_ips')) {
                $table->dropColumn('visitor_ips');
            }
        });
    }
};
