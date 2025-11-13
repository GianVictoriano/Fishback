<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('modules')->insertOrIgnore([
            [
                'name' => 'archives',
                'display_name' => 'Archives',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manage-media',
                'display_name' => 'Manage Media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('modules')->whereIn('name', ['archives', 'manage-media'])->delete();
    }
};
