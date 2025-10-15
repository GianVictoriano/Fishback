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
        Schema::table('group_chats', function (Blueprint $table) {
            $table->foreignId('scrum_board_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_chats', function (Blueprint $table) {
            $table->foreignId('scrum_board_id')->nullable(false)->change();
        });
    }
};
