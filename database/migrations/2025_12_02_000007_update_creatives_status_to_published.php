<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all creatives with draft status to published
        DB::table('creatives')
            ->where('status', 'draft')
            ->update([
                'status' => 'published',
                'published_at' => DB::raw('COALESCE(published_at, NOW())'),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally revert back to draft if needed
        // DB::table('creatives')
        //     ->where('status', 'published')
        //     ->whereNull('reviewed_at')
        //     ->update(['status' => 'draft']);
    }
};
