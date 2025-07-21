<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            ['name' => 'dashboard', 'display_name' => 'Dashboard'],
            ['name' => 'collaborate', 'display_name' => 'Collaborate'],
            ['name' => 'branding', 'display_name' => 'Branding'],
            ['name' => 'users', 'display_name' => 'User Management'],
            ['name' => 'review-content', 'display_name' => 'Review Content'],
            ['name' => 'create-content', 'display_name' => 'Create Content'],
        ];

        foreach ($modules as $module) {
            Module::firstOrCreate(['name' => $module['name']], $module);
        }
    }
}
