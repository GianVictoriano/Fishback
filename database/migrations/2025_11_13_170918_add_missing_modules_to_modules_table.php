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
                'name' => 'forum',
                'display_name' => 'Forum',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'folio',
                'display_name' => 'Folio',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'applicants',
                'display_name' => 'Applicants',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'requests',
                'display_name' => 'Requests',
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
        DB::table('modules')->whereIn('name', ['forum', 'folio', 'applicants', 'requests'])->delete();
    }
};
