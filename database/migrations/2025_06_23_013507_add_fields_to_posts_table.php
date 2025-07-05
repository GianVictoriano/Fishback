<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (!Schema::hasColumn('posts', 'summary')) {
                $table->string('summary');
            }

            if (!Schema::hasColumn('posts', 'article')) {
                $table->longText('article');
            }

            if (!Schema::hasColumn('posts', 'image_path')) {
                $table->string('image_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['summary', 'article', 'image_path']);
        });
    }
};
