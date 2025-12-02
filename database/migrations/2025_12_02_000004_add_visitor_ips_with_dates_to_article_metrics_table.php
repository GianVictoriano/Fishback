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
        Schema::table('article_metrics', function (Blueprint $table) {
            $table->json('visitor_ips_with_dates')->nullable()->after('visitor_ips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_metrics', function (Blueprint $table) {
            $table->dropColumn('visitor_ips_with_dates');
        });
    }
};
