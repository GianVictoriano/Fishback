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
            $table->dateTime('event_date')->nullable()->after('content');
            $table->string('event_location')->nullable()->after('event_date');
            $table->integer('num_journalists')->nullable()->after('event_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'event_location', 'num_journalists']);
        });
    }
};
