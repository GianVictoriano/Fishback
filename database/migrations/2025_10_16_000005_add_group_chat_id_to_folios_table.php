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
        Schema::table('folios', function (Blueprint $table) {
            $table->unsignedBigInteger('group_chat_id')->nullable()->after('status');
        });
        
        // Add foreign key constraint separately
        Schema::table('folios', function (Blueprint $table) {
            $table->foreign('group_chat_id')->references('id')->on('group_chats')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->dropForeign(['group_chat_id']);
            $table->dropColumn('group_chat_id');
        });
    }
};
