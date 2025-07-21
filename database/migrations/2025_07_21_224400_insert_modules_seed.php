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
        DB::table('modules')->insert([
            [
                'id' => 1,
                'name' => 'dashboard',
                'display_name' => 'Dashboard',
                'created_at' => '2025-07-21 05:47:13',
                'updated_at' => '2025-07-21 05:47:13',
            ],
            [
                'id' => 2,
                'name' => 'collaborate',
                'display_name' => 'Collaborate',
                'created_at' => '2025-07-21 05:47:13',
                'updated_at' => '2025-07-21 05:47:13',
            ],
            [
                'id' => 3,
                'name' => 'branding',
                'display_name' => 'Branding',
                'created_at' => '2025-07-21 05:47:13',
                'updated_at' => '2025-07-21 05:47:13',
            ],
            [
                'id' => 4,
                'name' => 'users',
                'display_name' => 'User Management',
                'created_at' => '2025-07-21 05:47:13',
                'updated_at' => '2025-07-21 05:47:13',
            ],
            [
                'id' => 5,
                'name' => 'review-content',
                'display_name' => 'Review Content',
                'created_at' => '2025-07-21 05:47:13',
                'updated_at' => '2025-07-21 05:47:13',
            ],
            [
                'id' => 6,
                'name' => 'create-content',
                'display_name' => 'Create Content',
                'created_at' => '2025-07-21 05:47:13',
                'updated_at' => '2025-07-21 05:47:13',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('modules')->whereIn('id', [1, 2, 3, 4, 5, 6])->delete();
    }
};
