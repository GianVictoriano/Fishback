<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_content', function (Blueprint $table) {
            $table->decimal('version', 8, 1)->default(1.0)->after('no_of_approval');
        });

        Schema::table('review_images', function (Blueprint $table) {
            $table->decimal('version', 8, 1)->default(1.0)->after('no_of_approval');
        });
    }

    public function down(): void
    {
        Schema::table('review_content', function (Blueprint $table) {
            $table->dropColumn('version');
        });

        Schema::table('review_images', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
