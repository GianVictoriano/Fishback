<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
            $table->timestamp('featured_at')->nullable()->after('is_featured');
            $table->index('is_featured');
            $table->index('featured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['featured_at']);
            $table->dropColumn(['is_featured', 'featured_at']);
        });
    }
};
