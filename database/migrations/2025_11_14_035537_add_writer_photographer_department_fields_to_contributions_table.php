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
        Schema::table('contributions', function (Blueprint $table) {
            $table->integer('num_writers')->nullable()->after('num_journalists');
            $table->integer('num_photographers')->nullable()->after('num_writers');
            $table->string('department')->nullable()->after('num_photographers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['num_writers', 'num_photographers', 'department']);
        });
    }
};
